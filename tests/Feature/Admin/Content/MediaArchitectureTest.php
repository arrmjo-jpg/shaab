<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\Article;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Media\EmbedResolver;
use App\Support\Media\WatermarkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
});

function mediaEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function mediaArticle(int $authorId): Article
{
    $cat = Category::create([
        'name' => 'تصنيف '.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'both',
    ]);

    return Article::create([
        'author_id' => $authorId,
        'primary_category_id' => $cat->id,
        'type' => 'news', 'status' => 'draft', 'locale' => 'ar',
        'title' => 'عنوان '.uniqid(), 'slug' => 'slug-'.uniqid(), 'content_json' => tiptapDoc(),
    ]);
}

/** رفع مباشر عبر الـ action وإسناد إلى pivot (مساعد الاختبارات). */
function attachMediaAsset(Article $article, string $collection, UploadedFile $file, User $user): MediaAsset
{
    $asset = (new StoreMediaAssetAction)->handle($file, $user);

    $maxPos = $article->mediaAssets()
        ->wherePivot('collection', $collection)
        ->max('article_media.position') ?? -1;

    $article->mediaAssets()->attach($asset->id, [
        'collection' => $collection,
        'position' => (int) $maxPos + 1,
    ]);

    return $asset;
}

// ─── Configurable disk ─────────────────────────────────────────────────

it('uses a configurable uploads disk (R2-swappable)', function (): void {
    expect(config('media-library.disk_name'))->toBe('uploads');
    expect(config('filesystems.disks.uploads'))->not->toBeNull();
    expect(config('filesystems.disks.uploads.driver'))->toBe('local');
});

// ─── StoreMediaAssetAction: UUID path + filename ───────────────────────

it('stores uploaded assets under assets/{uuid}/ with UUID filename', function (): void {
    [$u] = mediaEditor();

    $file = UploadedFile::fake()->image('cover.jpg', 800, 600);
    $asset = (new StoreMediaAssetAction)->handle($file, $u);

    expect($asset->path)->toStartWith('assets/');
    expect($asset->filename)->toMatch('/^[0-9a-f-]{36}\.jpg$/i');
    expect(Storage::disk($asset->disk)->exists($asset->path))->toBeTrue();
});

// ─── Article media endpoints ───────────────────────────────────────────

it('uploads a cover via API and exposes it in the resource', function (): void {
    [$u, $token] = mediaEditor();
    $a = mediaArticle($u->id);

    $res = $this->withToken($token)->post("/api/v1/admin/articles/{$a->id}/media", [
        'collection' => 'cover',
        'file' => UploadedFile::fake()->image('c.jpg', 600, 400),
    ], ['Accept' => 'application/json'])->assertCreated();

    expect($res->json('data.media.cover.url'))->not->toBeNull();
});

it('rejects a non-image file for image collections', function (): void {
    [$u, $token] = mediaEditor();
    $a = mediaArticle($u->id);

    $this->withToken($token)->post("/api/v1/admin/articles/{$a->id}/media", [
        'collection' => 'cover',
        'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('accepts an mp4 in the video collection', function (): void {
    [$u, $token] = mediaEditor();
    $a = mediaArticle($u->id);

    $this->withToken($token)->post("/api/v1/admin/articles/{$a->id}/media", [
        'collection' => 'video',
        'file' => UploadedFile::fake()->create('v.mp4', 500, 'video/mp4'),
    ], ['Accept' => 'application/json'])->assertCreated();
});

it('deletes an asset from the article pivot but keeps it in the library', function (): void {
    [$u, $token] = mediaEditor();
    $a = mediaArticle($u->id);
    $asset = attachMediaAsset($a, 'gallery', UploadedFile::fake()->image('g.jpg'), $u);

    $this->withToken($token)->deleteJson("/api/v1/admin/articles/{$a->id}/media/{$asset->id}")
        ->assertOk();

    // صف pivot زال
    expect($a->fresh()->mediaAssets()->where('media_assets.id', $asset->id)->exists())->toBeFalse();

    // الأصل لا يزال موجوداً في المكتبة المركزية
    expect(MediaAsset::find($asset->id))->not->toBeNull();
});

it('returns 404 when deleting a media asset not linked to the article', function (): void {
    [$u, $token] = mediaEditor();
    $a = mediaArticle($u->id);
    $b = mediaArticle($u->id);
    $assetOfB = attachMediaAsset($b, 'gallery', UploadedFile::fake()->image('g2.jpg'), $u);

    // محاولة حذف أصل تابع لمقال B من مقال A
    $this->withToken($token)->deleteJson("/api/v1/admin/articles/{$a->id}/media/{$assetOfB->id}")
        ->assertStatus(404);
});

// ─── Author avatar ─────────────────────────────────────────────────────

it('uploads an author avatar', function (): void {
    [, $token] = mediaEditor();
    $author = User::factory()->create(['is_writer' => true]);

    $res = $this->withToken($token)->post("/api/v1/admin/authors/{$author->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('a.png', 300, 300),
    ], ['Accept' => 'application/json'])->assertCreated();

    expect($res->json('data.avatar'))->not->toBeNull();
    expect($author->fresh()->getMedia('avatar'))->toHaveCount(1);
});

// ─── Embeds (allow-list) ───────────────────────────────────────────────

it('resolves allow-listed embeds and rejects others', function (): void {
    [, $token] = mediaEditor();

    $yt = $this->withToken($token)->postJson('/api/v1/admin/articles/embeds/resolve', [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertOk();
    expect($yt->json('data.provider'))->toBe('youtube');
    expect($yt->json('data.embed_url'))->toContain('youtube.com/embed/dQw4w9WgXcQ');

    expect(EmbedResolver::resolve('https://youtu.be/dQw4w9WgXcQ')['provider'])->toBe('youtube');
    expect(EmbedResolver::resolve('https://vimeo.com/76979871')['provider'])->toBe('vimeo');
    expect(EmbedResolver::resolve('https://x.com/u/status/123456789')['provider'])->toBe('twitter');

    $this->withToken($token)->postJson('/api/v1/admin/articles/embeds/resolve', [
        'url' => 'https://evil.example.com/x',
    ])->assertStatus(422);
});

// ─── Watermark-from-settings (no-op when disabled) ─────────────────────

it('watermark settings resolve to null when disabled/unconfigured', function (): void {
    expect(WatermarkSettings::current())->toBeNull();
});

// ─── Authorization ─────────────────────────────────────────────────────

it('denies media upload without a token', function (): void {
    $a = mediaArticle(User::factory()->create()->id);
    $this->post("/api/v1/admin/articles/{$a->id}/media", [
        'collection' => 'cover', 'file' => UploadedFile::fake()->image('c.jpg'),
    ], ['Accept' => 'application/json'])->assertStatus(401);
});

// ─── Media library list (GET /admin/media — for the studio Library tab) ─

it('lists library assets with type filter and search', function (): void {
    [$u, $token] = mediaEditor();

    (new StoreMediaAssetAction)->handle(UploadedFile::fake()->image('alpha.jpg', 640, 480), $u);
    (new StoreMediaAssetAction)->handle(UploadedFile::fake()->image('beta.jpg', 641, 480), $u);
    (new StoreMediaAssetAction)->handle(UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'), $u);

    // الكل
    $all = $this->withToken($token)->getJson('/api/v1/admin/media')->assertOk();
    expect($all->json('meta.pagination.total'))->toBe(3);

    // فلتر النوع image
    $images = $this->withToken($token)->getJson('/api/v1/admin/media?type=image')->assertOk();
    expect($images->json('meta.pagination.total'))->toBe(2);

    // فلتر النوع video
    $videos = $this->withToken($token)->getJson('/api/v1/admin/media?type=video')->assertOk();
    expect($videos->json('meta.pagination.total'))->toBe(1);

    // بحث بالاسم
    $search = $this->withToken($token)->getJson('/api/v1/admin/media?search=alpha')->assertOk();
    expect($search->json('meta.pagination.total'))->toBe(1);
});

it('requires media.view to list the library', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/media')->assertForbidden();
});
