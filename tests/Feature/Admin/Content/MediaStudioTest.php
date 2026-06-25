<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\Article;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
});

function studioEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function studioArticle(int $authorId): Article
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

/** مساعد: ينشئ MediaAsset ويُسنده إلى pivot المقال في المجموعة المحدّدة. */
function studioAttach(Article $article, string $collection, UploadedFile $file, User $user): MediaAsset
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

// ─── Media index ─────────────────────────────────────────────────────────

it('returns the article media blocks (cover/gallery/inline/video)', function (): void {
    [$u, $token] = studioEditor();
    $a = studioArticle($u->id);
    // أبعاد مميّزة لكل ملف ⇒ checksum مميّز (تفادي dedupe في StoreMediaAssetAction)
    studioAttach($a, 'cover', UploadedFile::fake()->image('cover.jpg', 800, 600), $u);
    studioAttach($a, 'gallery', UploadedFile::fake()->image('g1.jpg', 320, 240), $u);
    studioAttach($a, 'gallery', UploadedFile::fake()->image('g2.jpg', 321, 240), $u);

    $res = $this->withToken($token)->getJson("/api/v1/admin/articles/{$a->id}/media");

    $res->assertOk();
    assertSuccessContract($res);
    expect($res->json('data.cover'))->not->toBeNull();
    expect($res->json('data.cover.thumb'))->not->toBeNull();
    expect($res->json('data.gallery'))->toHaveCount(2);
    expect($res->json('data.inline'))->toHaveCount(0);
    expect($res->json('data.video'))->toHaveCount(0);
});

it('requires articles.edit to read the media studio', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('t', ['admin'])->plainTextToken;
    $a = studioArticle($user->id);

    $this->withToken($token)->getJson("/api/v1/admin/articles/{$a->id}/media")
        ->assertForbidden();
});

// ─── Reorder ───────────────────────────────────────────────────────────

it('reorders a gallery and the list reflects the new order', function (): void {
    [$u, $token] = studioEditor();
    $a = studioArticle($u->id);
    $m1 = studioAttach($a, 'gallery', UploadedFile::fake()->image('a.jpg', 310, 240), $u);
    $m2 = studioAttach($a, 'gallery', UploadedFile::fake()->image('b.jpg', 311, 240), $u);
    $m3 = studioAttach($a, 'gallery', UploadedFile::fake()->image('c.jpg', 312, 240), $u);

    // Default order: m1, m2, m3 → reorder to m3, m1, m2
    $res = $this->withToken($token)->patchJson(
        "/api/v1/admin/articles/{$a->id}/media/reorder",
        ['collection' => 'gallery', 'ids' => [$m3->id, $m1->id, $m2->id]],
    );

    $res->assertOk();
    $ids = collect($res->json('data.gallery'))->pluck('id')->all();
    expect($ids)->toBe([$m3->id, $m1->id, $m2->id]);

    // Persisted: a fresh index call returns the same order
    $again = $this->withToken($token)->getJson("/api/v1/admin/articles/{$a->id}/media");
    expect(collect($again->json('data.gallery'))->pluck('id')->all())
        ->toBe([$m3->id, $m1->id, $m2->id]);
});

it('ignores ids that do not belong to the collection', function (): void {
    [$u, $token] = studioEditor();
    $a = studioArticle($u->id);
    $m1 = studioAttach($a, 'gallery', UploadedFile::fake()->image('a.jpg', 330, 240), $u);
    $m2 = studioAttach($a, 'gallery', UploadedFile::fake()->image('b.jpg', 331, 240), $u);

    // 99999 is a foreign id — must be skipped, not error
    $res = $this->withToken($token)->patchJson(
        "/api/v1/admin/articles/{$a->id}/media/reorder",
        ['collection' => 'gallery', 'ids' => [$m2->id, 99999, $m1->id]],
    );

    $res->assertOk();
    expect(collect($res->json('data.gallery'))->pluck('id')->all())->toBe([$m2->id, $m1->id]);
});

it('validates the reorder collection and ids', function (): void {
    [$u, $token] = studioEditor();
    $a = studioArticle($u->id);

    $this->withToken($token)->patchJson(
        "/api/v1/admin/articles/{$a->id}/media/reorder",
        ['collection' => 'bogus', 'ids' => [1]],
    )->assertUnprocessable();

    $this->withToken($token)->patchJson(
        "/api/v1/admin/articles/{$a->id}/media/reorder",
        ['collection' => 'gallery', 'ids' => []],
    )->assertUnprocessable();
});

it('requires articles.edit to reorder', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('t', ['admin'])->plainTextToken;
    $a = studioArticle($user->id);

    $this->withToken($token)->patchJson(
        "/api/v1/admin/articles/{$a->id}/media/reorder",
        ['collection' => 'gallery', 'ids' => [1]],
    )->assertForbidden();
});

// ─── Cover deduplication ───────────────────────────────────────────────

it('replaces the cover when a second cover is uploaded', function (): void {
    [$u, $token] = studioEditor();
    $a = studioArticle($u->id);

    // رفع الغلاف الأوّل
    $this->withToken($token)->post("/api/v1/admin/articles/{$a->id}/media", [
        'collection' => 'cover',
        'file' => UploadedFile::fake()->image('c1.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    // رفع الغلاف الثاني — يجب أن يُستبدَل الأوّل
    $this->withToken($token)->post("/api/v1/admin/articles/{$a->id}/media", [
        'collection' => 'cover',
        'file' => UploadedFile::fake()->image('c2.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    expect(
        $a->fresh()->mediaAssets()->wherePivot('collection', 'cover')->count()
    )->toBe(1);
});
