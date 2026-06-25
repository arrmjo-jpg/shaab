<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreExternalVideoAction;
use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\Article;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    config(['media-library.disk_name' => 'uploads']);
});

function govEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function govAsset(User $u, int $w = 600, int $h = 400): MediaAsset
{
    return (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->image('g-'.uniqid().'.jpg', $w, $h),
        $u,
    );
}

function govArticleWith(MediaAsset $asset, User $u): Article
{
    $cat = Category::create([
        'name' => 'قسم '.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'both',
    ]);
    $a = Article::create([
        'author_id' => $u->id, 'primary_category_id' => $cat->id, 'type' => 'news',
        'status' => 'draft', 'locale' => 'ar', 'title' => 'خبر مع وسائط',
        'slug' => 's-'.uniqid(), 'content' => '<p>x</p>', 'content_json' => tiptapDoc(),
    ]);
    $a->mediaAssets()->attach($asset->id, ['collection' => 'gallery', 'position' => 0]);

    return $a;
}

// ─── Metadata editing ────────────────────────────────────────────────────

it('updates editorial metadata without re-uploading', function (): void {
    [$u, $token] = govEditor();
    $asset = govAsset($u);

    $res = $this->withToken($token)->patchJson("/api/v1/admin/media/{$asset->uuid}", [
        'alt' => 'نص بديل',
        'caption' => 'تعليق توضيحي',
        'credit' => 'المصوّر',
        'source' => 'وكالة',
    ])->assertOk();

    expect($res->json('data.alt'))->toBe('نص بديل');
    $fresh = $asset->fresh();
    expect($fresh->caption)->toBe('تعليق توضيحي');
    expect($fresh->credit)->toBe('المصوّر');
    // metadata change is audited (alt/caption/credit/source in auditAttributes)
    expect(Activity::where('log_name', 'media')->where('event', 'updated')->count())->toBeGreaterThan(0);
});

// ─── Usage visibility ──────────────────────────────────────────────────────

it('reports usage_count in the list and where-used in detail', function (): void {
    [$u, $token] = govEditor();
    $asset = govAsset($u);
    $article = govArticleWith($asset, $u);

    $list = $this->withToken($token)->getJson('/api/v1/admin/media')->assertOk();
    $row = collect($list->json('data'))->firstWhere('id', $asset->id);
    expect($row['usage_count'])->toBe(1);

    $detail = $this->withToken($token)->getJson("/api/v1/admin/media/{$asset->uuid}")->assertOk();
    expect($detail->json('data.usage_count'))->toBe(1);
    expect($detail->json('data.usages.0.context'))->toBe('article');
    expect($detail->json('data.usages.0.title'))->toBe($article->title);
});

// ─── Delete guard + delete ─────────────────────────────────────────────────

it('blocks deletion of an in-use asset without force', function (): void {
    [$u, $token] = govEditor();
    $asset = govAsset($u);
    govArticleWith($asset, $u);

    $this->withToken($token)->deleteJson("/api/v1/admin/media/{$asset->uuid}")
        ->assertStatus(409)
        ->assertJsonPath('errors.usage_count', 1);

    expect(MediaAsset::find($asset->id))->not->toBeNull();
});

it('force-deletes an in-use asset and cascades the attachment', function (): void {
    [$u, $token] = govEditor();
    $asset = govAsset($u);
    $article = govArticleWith($asset, $u);

    $this->withToken($token)->deleteJson("/api/v1/admin/media/{$asset->uuid}?force=1")->assertOk();

    expect(MediaAsset::find($asset->id))->toBeNull();
    expect($article->fresh()->mediaAssets()->count())->toBe(0);
    expect(Activity::where('log_name', 'media')->where('event', 'deleted')->count())->toBe(1);
});

it('deletes an unused asset directly', function (): void {
    [$u, $token] = govEditor();
    $asset = govAsset($u);

    $this->withToken($token)->deleteJson("/api/v1/admin/media/{$asset->uuid}")->assertOk();
    expect(MediaAsset::find($asset->id))->toBeNull();
});

it('requires media.delete permission', function (): void {
    [$u] = govEditor();
    $asset = govAsset($u);

    $other = User::factory()->create();
    $other->assignRole('journalist'); // no media.delete
    $token = $other->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->deleteJson("/api/v1/admin/media/{$asset->uuid}")->assertForbidden();
});

// ─── Search / filter ───────────────────────────────────────────────────────

it('searches across metadata fields and filters by type', function (): void {
    [$u, $token] = govEditor();
    $a1 = govAsset($u, 320, 240);
    $a1->forceFill(['caption' => 'اعتصام في الساحة'])->save();
    govAsset($u, 321, 240);
    (new StoreExternalVideoAction)->handle('https://youtu.be/dQw4w9WgXcQ', $u);

    $byCaption = $this->withToken($token)->getJson('/api/v1/admin/media?search=اعتصام')->assertOk();
    expect($byCaption->json('meta.pagination.total'))->toBe(1);

    $external = $this->withToken($token)->getJson('/api/v1/admin/media?type=external')->assertOk();
    expect($external->json('meta.pagination.total'))->toBe(1);
    expect($external->json('data.0.provider'))->toBe('youtube');
});
