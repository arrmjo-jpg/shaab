<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\ArticleLiveUpdate;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Cache::flush();
});

function pubLiveCat(array $attrs = []): Category
{
    return Category::create(array_merge([
        'name' => 'تصنيف '.uniqid(),
        'locale' => 'ar',
        'status' => 'active',
    ], $attrs));
}

function pubLiveArticle(Category $cat, array $attrs = []): Article
{
    return Article::create(array_merge([
        'title' => 'تغطية '.uniqid(),
        'locale' => $cat->locale,
        'type' => 'live',
        'status' => 'published',
        'primary_category_id' => $cat->id,
        'author_id' => User::factory()->create()->id,
        'content_json' => tiptapDoc(),
        'content' => '<p>مقدمة</p>',
        'published_at' => now()->subHour(),
    ], $attrs))->fresh();
}

function makeUpdate(Article $a, array $attrs = []): ArticleLiveUpdate
{
    return ArticleLiveUpdate::create(array_merge([
        'article_id' => $a->id,
        'author_id' => $a->author_id,
        'content_json' => tiptapDoc('سطر'),
        'content' => '<p>سطر</p>',
        'happened_at' => now(),
        'is_pinned' => false,
    ], $attrs));
}

// ─── Separate-concerns: metadata endpoint stays clean ──────────────────

it('serves live article metadata via the existing article detail endpoint without dumping updates', function (): void {
    $cat = pubLiveCat();
    $article = pubLiveArticle($cat);
    makeUpdate($article, ['title' => 'لا يجب أن يظهر هنا']);

    $res = $this->getJson("/api/v1/ar/articles/{$article->slug}");

    $res->assertOk();
    expect($res->json('data.type'))->toBe('live');
    // The article detail must NOT inline the live timeline (separate concern)
    expect(array_keys($res->json('data')))->not->toContain('live_updates');
    expect(array_keys($res->json('data')))->not->toContain('updates');
});

// ─── Updates endpoint ──────────────────────────────────────────────────

it('returns the live timeline pinned-first then chronological', function (): void {
    $cat = pubLiveCat();
    $article = pubLiveArticle($cat);
    makeUpdate($article, ['title' => 'A-old', 'happened_at' => now()->subHours(3)]);
    makeUpdate($article, ['title' => 'B-new', 'happened_at' => now()->subHour()]);
    makeUpdate($article, ['title' => 'C-pin', 'happened_at' => now()->subHours(5), 'is_pinned' => true]);

    $res = $this->getJson("/api/v1/ar/articles/{$article->slug}/live-updates");

    $res->assertOk();
    assertSuccessContract($res);
    $titles = collect($res->json('data'))->pluck('title')->all();
    expect($titles)->toBe(['C-pin', 'B-new', 'A-old']);
});

it('does not leak content_json or author_id in public updates', function (): void {
    $cat = pubLiveCat();
    $article = pubLiveArticle($cat);
    makeUpdate($article, ['title' => 'x']);

    $res = $this->getJson("/api/v1/ar/articles/{$article->slug}/live-updates");
    $row = $res->json('data.0');

    expect(array_keys($row))->not->toContain('content_json');
    expect(array_keys($row))->not->toContain('author_id');
    expect(array_keys($row))->toContain('content_html');
});

it('paginates the timeline', function (): void {
    $cat = pubLiveCat();
    $article = pubLiveArticle($cat);
    for ($i = 0; $i < 5; $i++) {
        makeUpdate($article, ['happened_at' => now()->subMinutes($i)]);
    }

    $res = $this->getJson("/api/v1/ar/articles/{$article->slug}/live-updates?per_page=2&page=1");

    $res->assertOk();
    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('meta.pagination.total'))->toBe(5);
    expect($res->json('meta.pagination.total_pages'))->toBe(3);
});

it('returns 404 for an unpublished or missing live article', function (): void {
    $cat = pubLiveCat();
    $draft = pubLiveArticle($cat, ['status' => 'draft', 'published_at' => null]);

    $this->getJson("/api/v1/ar/articles/{$draft->slug}/live-updates")->assertNotFound();
    $this->getJson('/api/v1/ar/articles/nope/live-updates')->assertNotFound();
});

it('rejects an unsupported locale', function (): void {
    $this->getJson('/api/v1/de/articles/x/live-updates')->assertNotFound(); // route constraint
});

// ─── ETag / 304 polling ────────────────────────────────────────────────

it('returns an ETag and a short live Cache-Control', function (): void {
    $cat = pubLiveCat();
    $article = pubLiveArticle($cat);
    makeUpdate($article);

    $res = $this->getJson("/api/v1/ar/articles/{$article->slug}/live-updates");

    $res->assertOk();
    expect($res->headers->get('ETag'))->not->toBeNull();
    $cc = $res->headers->get('Cache-Control');
    expect($cc)->toContain('s-maxage=15');
    expect($cc)->toContain('max-age=5');
});

it('responds 304 when the client ETag still matches', function (): void {
    $cat = pubLiveCat();
    $article = pubLiveArticle($cat);
    makeUpdate($article);

    $first = $this->getJson("/api/v1/ar/articles/{$article->slug}/live-updates");
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    $second = $this->getJson(
        "/api/v1/ar/articles/{$article->slug}/live-updates",
        ['If-None-Match' => $etag],
    );

    $second->assertStatus(304);
    expect($second->getContent())->toBe('');
});

it('changes the ETag (no 304) after a new update is posted', function (): void {
    $cat = pubLiveCat();
    $article = pubLiveArticle($cat);
    makeUpdate($article);

    $first = $this->getJson("/api/v1/ar/articles/{$article->slug}/live-updates");
    $etag = $first->headers->get('ETag');

    // New update changes the fingerprint → ETag must differ → 200, not 304
    makeUpdate($article, ['title' => 'جديد', 'happened_at' => now()->addSecond()]);

    $second = $this->getJson(
        "/api/v1/ar/articles/{$article->slug}/live-updates",
        ['If-None-Match' => $etag],
    );

    $second->assertOk();
    expect($second->headers->get('ETag'))->not->toBe($etag);
});

it('changes the ETag after an existing update is edited (content change)', function (): void {
    $cat = pubLiveCat();
    $article = pubLiveArticle($cat);
    $u = makeUpdate($article, ['title' => 'قبل']);

    $first = $this->getJson("/api/v1/ar/articles/{$article->slug}/live-updates");
    $etag = $first->headers->get('ETag');

    // Second-precision fingerprint (count + MAX(updated_at)). A realistic edit
    // lands in a later second, so MAX(updated_at) advances → fingerprint/ETag
    // change. (Same-second edits are a negligible, documented edge case.)
    $this->travel(2)->seconds();
    $u->update(['title' => 'بعد']);

    $second = $this->getJson(
        "/api/v1/ar/articles/{$article->slug}/live-updates",
        ['If-None-Match' => $etag],
    );

    $second->assertOk();
    expect($second->headers->get('ETag'))->not->toBe($etag);

    $this->travelBack();
});

// ─── Cache invalidation alignment ──────────────────────────────────────

it('reflects a new update immediately (live_updates tag flush on admin create)', function (): void {
    $cat = pubLiveCat();
    $article = pubLiveArticle($cat);
    makeUpdate($article, ['title' => 'أول']);

    // Warm public cache
    expect($this->getJson("/api/v1/ar/articles/{$article->slug}/live-updates")->json('data'))
        ->toHaveCount(1);

    // Admin posts a new update via the editorial endpoint
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson("/api/v1/admin/articles/{$article->id}/live-updates", [
        'content_json' => tiptapDoc('ثاني'),
    ])->assertCreated();

    // Public timeline reflects it without staleness
    expect($this->getJson("/api/v1/ar/articles/{$article->slug}/live-updates")->json('data'))
        ->toHaveCount(2);
});
