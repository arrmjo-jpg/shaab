<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\ArticleLiveUpdate;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Cache::flush();
});

function liveAdminToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

/** Writer with articles.edit but NOT editorial — should be blocked by the guard. */
function liveWriterToken(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->givePermissionTo('articles.edit');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function liveCat(array $attrs = []): Category
{
    return Category::create(array_merge([
        'name' => 'تصنيف '.uniqid(),
        'locale' => 'ar',
        'status' => 'active',
        'scope' => 'news',
    ], $attrs));
}

function liveArticle(Category $cat, array $attrs = []): Article
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

function liveUpdatePayload(array $o = []): array
{
    return array_merge([
        'title' => 'تطوّر جديد',
        'content_json' => tiptapDoc('سطر تحديث'),
    ], $o);
}

// ─── Create ──────────────────────────────────────────────────────────────

it('lets an editorial user add a live update and records activity', function (): void {
    [$admin, $token] = liveAdminToken();
    $article = liveArticle(liveCat());

    $res = $this->withToken($token)
        ->postJson("/api/v1/admin/articles/{$article->id}/live-updates", liveUpdatePayload());

    $res->assertCreated();
    assertSuccessContract($res);
    expect($res->json('data.title'))->toBe('تطوّر جديد');
    expect($res->json('data.author.id'))->toBe($admin->id);
    expect($res->json('data.content_html'))->toContain('سطر تحديث');

    expect(ArticleLiveUpdate::where('article_id', $article->id)->count())->toBe(1);

    // model-audit convention: the change MUST appear in activity_log
    expect(Activity::where('log_name', 'live_update')->count())->toBe(1);
});

it('sets happened_at to now when omitted', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());

    $res = $this->withToken($token)
        ->postJson("/api/v1/admin/articles/{$article->id}/live-updates", liveUpdatePayload());

    $res->assertCreated();
    expect($res->json('data.happened_at'))->not->toBeNull();
});

it('reorders timeline updates via move up/down (swaps positions)', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());

    $a = $this->withToken($token)->postJson(
        "/api/v1/admin/articles/{$article->id}/live-updates",
        liveUpdatePayload(['title' => 'A']),
    )->json('data.id');
    $b = $this->withToken($token)->postJson(
        "/api/v1/admin/articles/{$article->id}/live-updates",
        liveUpdatePayload(['title' => 'B']),
    )->json('data.id');

    // B was created last → higher position (top). Move it down to swap with A.
    $this->withToken($token)->patchJson(
        "/api/v1/admin/articles/{$article->id}/live-updates/{$b}/move",
        ['direction' => 'down'],
    )->assertOk();

    expect(ArticleLiveUpdate::find($b)->position)->toBeLessThan(ArticleLiveUpdate::find($a)->position);
});

it('rejects an invalid move direction', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());
    $id = $this->withToken($token)->postJson(
        "/api/v1/admin/articles/{$article->id}/live-updates",
        liveUpdatePayload(),
    )->json('data.id');

    $this->withToken($token)->patchJson(
        "/api/v1/admin/articles/{$article->id}/live-updates/{$id}/move",
        ['direction' => 'sideways'],
    )->assertStatus(422);
});

it('persists breaking + featured flags on a live update', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());

    $res = $this->withToken($token)->postJson(
        "/api/v1/admin/articles/{$article->id}/live-updates",
        liveUpdatePayload(['is_breaking' => true, 'is_featured' => true]),
    )->assertCreated();

    expect($res->json('data.is_breaking'))->toBeTrue();
    expect($res->json('data.is_featured'))->toBeTrue();

    $id = $res->json('data.id');
    expect(ArticleLiveUpdate::find($id)->is_breaking)->toBeTrue();

    // toggle off via update
    $this->withToken($token)->putJson(
        "/api/v1/admin/articles/{$article->id}/live-updates/{$id}",
        ['is_breaking' => false],
    )->assertOk();
    expect(ArticleLiveUpdate::find($id)->fresh()->is_breaking)->toBeFalse();
});

it('honors an explicit happened_at and pin flag on create', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());
    $when = now()->subMinutes(30);

    $res = $this->withToken($token)->postJson(
        "/api/v1/admin/articles/{$article->id}/live-updates",
        liveUpdatePayload(['happened_at' => $when->toISOString(), 'is_pinned' => true]),
    );

    $res->assertCreated();
    expect($res->json('data.is_pinned'))->toBeTrue();
});

it('flushes the live_updates cache on create', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());

    Cache::tags(['live_updates'])->put('probe', 'stale', 60);

    $this->withToken($token)
        ->postJson("/api/v1/admin/articles/{$article->id}/live-updates", liveUpdatePayload())
        ->assertCreated();

    expect(Cache::tags(['live_updates'])->get('probe'))->toBeNull();
});

// ─── Guard rules ───────────────────────────────────────────────────────

it('rejects live updates on a non-live article (422)', function (): void {
    [, $token] = liveAdminToken();
    $newsArticle = liveArticle(liveCat(), ['type' => 'news']);

    $this->withToken($token)
        ->postJson("/api/v1/admin/articles/{$newsArticle->id}/live-updates", liveUpdatePayload())
        ->assertUnprocessable();
});

it('blocks a writer (non-editorial) even with articles.edit (403)', function (): void {
    [, $token] = liveWriterToken();
    $article = liveArticle(liveCat());

    $this->withToken($token)
        ->postJson("/api/v1/admin/articles/{$article->id}/live-updates", liveUpdatePayload())
        ->assertForbidden();
});

it('rejects an invalid TipTap document', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());

    $this->withToken($token)->postJson(
        "/api/v1/admin/articles/{$article->id}/live-updates",
        ['content_json' => ['type' => 'doc', 'content' => [['type' => 'evilNode']]]],
    )->assertUnprocessable();
});

it('requires the articles.edit permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('t', ['admin'])->plainTextToken;
    $article = liveArticle(liveCat());

    $this->withToken($token)
        ->postJson("/api/v1/admin/articles/{$article->id}/live-updates", liveUpdatePayload())
        ->assertForbidden();
});

// ─── List / ordering ───────────────────────────────────────────────────

it('lists updates pinned-first then chronological desc', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());

    ArticleLiveUpdate::create([
        'article_id' => $article->id, 'content_json' => tiptapDoc('قديم'),
        'happened_at' => now()->subHours(3), 'is_pinned' => false, 'title' => 'A-old',
    ]);
    ArticleLiveUpdate::create([
        'article_id' => $article->id, 'content_json' => tiptapDoc('حديث'),
        'happened_at' => now()->subHour(), 'is_pinned' => false, 'title' => 'B-new',
    ]);
    ArticleLiveUpdate::create([
        'article_id' => $article->id, 'content_json' => tiptapDoc('مثبّت'),
        'happened_at' => now()->subHours(5), 'is_pinned' => true, 'title' => 'C-pinned',
    ]);

    $res = $this->withToken($token)->getJson("/api/v1/admin/articles/{$article->id}/live-updates");

    $res->assertOk();
    $titles = collect($res->json('data'))->pluck('title')->all();
    // pinned first, then newest happened_at, then older
    expect($titles)->toBe(['C-pinned', 'B-new', 'A-old']);
});

// ─── Update ──────────────────────────────────────────────────────────────

it('updates an entry title, content and pin', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());
    $update = ArticleLiveUpdate::create([
        'article_id' => $article->id, 'content_json' => tiptapDoc('قبل'),
        'happened_at' => now(), 'title' => 'قبل',
    ]);

    $res = $this->withToken($token)->putJson(
        "/api/v1/admin/articles/{$article->id}/live-updates/{$update->id}",
        ['title' => 'بعد', 'is_pinned' => true, 'content_json' => tiptapDoc('بعد')],
    );

    $res->assertOk();
    expect($res->json('data.title'))->toBe('بعد');
    expect($res->json('data.is_pinned'))->toBeTrue();
    expect($res->json('data.content_html'))->toContain('بعد');
});

it('returns 404 when the update belongs to a different article', function (): void {
    [, $token] = liveAdminToken();
    $a1 = liveArticle(liveCat());
    $a2 = liveArticle(liveCat());
    $update = ArticleLiveUpdate::create([
        'article_id' => $a2->id, 'content_json' => tiptapDoc('x'), 'happened_at' => now(),
    ]);

    $this->withToken($token)->putJson(
        "/api/v1/admin/articles/{$a1->id}/live-updates/{$update->id}",
        ['title' => 'y'],
    )->assertNotFound();
});

// ─── Delete ──────────────────────────────────────────────────────────────

it('deletes a live update', function (): void {
    [, $token] = liveAdminToken();
    $article = liveArticle(liveCat());
    $update = ArticleLiveUpdate::create([
        'article_id' => $article->id, 'content_json' => tiptapDoc('x'), 'happened_at' => now(),
    ]);

    $this->withToken($token)
        ->deleteJson("/api/v1/admin/articles/{$article->id}/live-updates/{$update->id}")
        ->assertOk();

    expect(ArticleLiveUpdate::find($update->id))->toBeNull();
});

it('cascade-deletes live updates when the parent article is removed', function (): void {
    $article = liveArticle(liveCat());
    ArticleLiveUpdate::create([
        'article_id' => $article->id, 'content_json' => tiptapDoc('x'), 'happened_at' => now(),
    ]);

    $article->delete(); // soft delete on Article does NOT cascade; force a hard delete to verify FK
    $article->forceDelete();

    expect(ArticleLiveUpdate::where('article_id', $article->id)->count())->toBe(0);
});
