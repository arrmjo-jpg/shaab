<?php

declare(strict_types=1);

use App\Actions\Admin\Media\DeleteMediaAssetAction;
use App\Actions\Admin\Media\PruneOrphanMediaAssetsAction;
use App\Models\Article;
use App\Models\Broadcast;
use App\Models\BroadcastCategory;
use App\Models\Category;
use App\Models\Epaper;
use App\Models\EpaperVersion;
use App\Models\MediaAsset;
use App\Models\Reel;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\VideoPlaylist;
use App\Support\Media\MediaUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    config(['media-library.disk_name' => 'uploads']);
});

/** أصل مكتبة (path assets/) أقدم من TTL الافتراضي (48س) — مؤهَّل للتنظيف ما لم يُستخدَم. */
function muAsset(): MediaAsset
{
    $asset = MediaAsset::create([
        'uuid' => 'mu-'.uniqid(),
        'disk' => 'uploads',
        'path' => 'assets/'.uniqid().'/file.bin',
        'filename' => 'file.bin',
        'original_name' => 'file.bin',
        'extension' => 'bin',
        'mime_type' => 'application/octet-stream',
        'size' => 1024,
        'visibility' => 'public',
    ]);
    $asset->forceFill(['created_at' => now()->subDays(3)])->save();

    return $asset;
}

function muArticle(): Article
{
    $cat = Category::create([
        'name' => 'ق '.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'both',
    ]);

    return Article::create([
        'author_id' => User::factory()->create()->id,
        'primary_category_id' => $cat->id,
        'type' => 'news', 'status' => 'draft', 'locale' => 'ar',
        'title' => 'مقال '.uniqid(), 'slug' => 'a-'.uniqid(),
        'content' => '<p>x</p>', 'content_json' => ['type' => 'doc', 'content' => []],
    ]);
}

function muEpaper(?int $assetId = null): Epaper
{
    static $n = 0;
    $n++;

    return Epaper::create([
        'locale' => 'ar',
        'issue_number' => 9000 + $n,
        'title' => 'عدد '.uniqid(),
        'slug' => 'e-'.uniqid(),
        'publication_date' => now()->subDay()->toDateString(),
        'status' => 'published',
        'published_at' => now()->subDay(),
        'media_asset_id' => $assetId,
    ]);
}

/** عقد الحماية الموحّد: الحذف اليدوي محظور (deleted=false, usage>0) + التنظيف لا يمسّه. */
function muAssertProtected(MediaAsset $asset): void
{
    $del = (new DeleteMediaAssetAction)->handle($asset, force: false);
    expect($del['deleted'])->toBeFalse()
        ->and($del['usage_count'])->toBeGreaterThan(0);

    (new PruneOrphanMediaAssetsAction)->handle();
    expect(MediaAsset::find($asset->id))->not->toBeNull();
}

// ─── حماية كل مستهلك فعليّ (حذف يدوي + تنظيف) ─────────────────────────────────

it('protects an asset used by an article (shared pivot)', function (): void {
    $asset = muAsset();
    $article = muArticle();
    $asset->articles()->attach($article->id, ['collection' => 'gallery', 'position' => 0]);

    muAssertProtected($asset);
});

it('protects an asset used as an article og:image', function (): void {
    $asset = muAsset();
    muArticle()->forceFill(['og_image_id' => $asset->id])->save();

    muAssertProtected($asset);
});

it('protects an asset used by a reel', function (): void {
    $asset = muAsset();
    Reel::create(['title' => 'ريل '.uniqid(), 'locale' => 'ar', 'status' => 'draft', 'media_asset_id' => $asset->id]);

    muAssertProtected($asset);
});

it('protects an asset used by a video', function (): void {
    $asset = muAsset();
    Video::factory()->create(['media_asset_id' => $asset->id]);

    muAssertProtected($asset);
});

it('protects an asset used as a video category cover', function (): void {
    $asset = muAsset();
    VideoCategory::factory()->create()->forceFill(['cover_media_id' => $asset->id])->save();

    muAssertProtected($asset);
});

it('protects an asset used as a video playlist cover', function (): void {
    $asset = muAsset();
    VideoPlaylist::factory()->create()->forceFill(['cover_media_id' => $asset->id])->save();

    muAssertProtected($asset);
});

it('protects an asset used as a broadcast cover', function (): void {
    $asset = muAsset();
    Broadcast::factory()->create()->forceFill(['cover_media_id' => $asset->id])->save();

    muAssertProtected($asset);
});

it('protects an asset used as a broadcast category cover', function (): void {
    $asset = muAsset();
    BroadcastCategory::factory()->create()->forceFill(['cover_media_id' => $asset->id])->save();

    muAssertProtected($asset);
});

it('protects an asset used by an epaper (current document)', function (): void {
    $asset = muAsset();
    muEpaper($asset->id);

    muAssertProtected($asset);
});

it('protects an asset used by an epaper version (replace-pdf history)', function (): void {
    $versionAsset = muAsset();
    $epaper = muEpaper(muAsset()->id); // عدد بأصلٍ مستقلّ للوثيقة الحاليّة
    EpaperVersion::create([
        'epaper_id' => $epaper->id,
        'version' => 2,
        'media_asset_id' => $versionAsset->id,
    ]);

    muAssertProtected($versionAsset);
});

// ─── المالك المحذوف ناعماً يبقى يحمي وسائطه (اتّساق مع التنظيف) ────────────────

it('keeps protecting media of a soft-deleted video owner', function (): void {
    $asset = muAsset();
    $video = Video::factory()->create(['media_asset_id' => $asset->id]);
    $video->delete(); // soft delete — قابل للاسترجاع

    muAssertProtected($asset);
});

// ─── الأصل اليتيم فعلاً يُنظَّف/يُحذف (لا حماية كاذبة) ─────────────────────────

it('prunes a truly orphan asset (no consumer)', function (): void {
    $asset = muAsset();

    $pruned = (new PruneOrphanMediaAssetsAction)->handle();

    expect($pruned)->toBeGreaterThanOrEqual(1);
    expect(MediaAsset::find($asset->id))->toBeNull();
});

it('deletes a truly orphan asset directly (no force needed)', function (): void {
    $asset = muAsset();

    $del = (new DeleteMediaAssetAction)->handle($asset, force: false);

    expect($del['deleted'])->toBeTrue()
        ->and($del['usage_count'])->toBe(0);
    expect(MediaAsset::find($asset->id))->toBeNull();
});

// ─── force يتجاوز الحارس (سلوك مقصود محفوظ) ───────────────────────────────────

it('force-deletes an in-use asset (guard overridden as intended)', function (): void {
    $asset = muAsset();
    Video::factory()->create(['media_asset_id' => $asset->id]);

    $del = (new DeleteMediaAssetAction)->handle($asset, force: true);

    expect($del['deleted'])->toBeTrue()
        ->and($del['usage_count'])->toBeGreaterThan(0);
    expect(MediaAsset::find($asset->id))->toBeNull();
});

// ─── عدّاد الاستخدام في الواجهة يعكس الواقع (كان يبلّغ صفراً للفيديو/الأعداد) ───

it('reports a real usage_count for a video-only asset (withCount source of truth)', function (): void {
    $asset = muAsset();
    Video::factory()->create(['media_asset_id' => $asset->id]);

    $reloaded = MediaAsset::query()
        ->whereKey($asset->id)
        ->withCount(MediaUsage::countSelectors())
        ->first();

    expect((int) $reloaded->videos_count)->toBe(1);
    expect(MediaUsage::sumLoadedCounts($reloaded))->toBe(1);
});

it('exposes a real usage_count for a video-only asset over the list endpoint', function (): void {
    $u = User::factory()->create();
    $u->assignRole('super_admin');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $asset = muAsset();
    Video::factory()->create(['media_asset_id' => $asset->id]);

    $list = $this->withToken($token)->getJson('/api/v1/admin/media')->assertOk();
    $row = collect($list->json('data'))->firstWhere('id', $asset->id);

    expect($row)->not->toBeNull();
    expect($row['usage_count'])->toBe(1);
});
