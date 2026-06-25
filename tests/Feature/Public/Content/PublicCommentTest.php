<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(GeneralSettings::class)->fill(['comments_enabled' => true])->save();
});

function pcArticle(bool $commentsEnabled = true): Article
{
    $cat = Category::create([
        'name' => 'c-'.uniqid(),
        'slug' => 'cat-'.uniqid(),
        'locale' => 'ar',
        'scope' => 'both',
        'status' => 'active',
    ]);

    return Article::create([
        'primary_category_id' => $cat->id,
        'type' => 'news',
        'status' => 'published',
        'locale' => 'ar',
        'title' => 'مقال '.uniqid(),
        'slug' => 'a-'.Str::random(10),
        'published_at' => now()->subDay(),
        'comments_enabled' => $commentsEnabled,
    ]);
}

function pcComment(Article $a, array $attrs = []): Comment
{
    return Comment::create(array_merge([
        'commentable_type' => $a->getMorphClass(),
        'commentable_id' => $a->id,
        'user_id' => null,
        'author_name' => 'زائر',
        'author_email' => 'guest@example.com',
        'body' => 'تعليق '.uniqid(),
        'status' => 'approved',
    ], $attrs));
}

function pcUrl(Article $a): string
{
    return "/api/v1/ar/articles/{$a->slug}/comments";
}

/** @return array<string,mixed> */
function pcGuestPayload(array $overrides = []): array
{
    return array_merge([
        'body' => 'تعليق عام جيّد ومفيد',
        'author_name' => 'قارئ',
        'author_email' => 'reader@example.com',
    ], $overrides);
}

// ─── List: approved only ───────────────────────────────────────────────────

it('lists only approved top-level comments with approved replies (no PII)', function (): void {
    $a = pcArticle();
    $approved = pcComment($a, ['status' => 'approved', 'body' => 'معتمَد']);
    pcComment($a, ['status' => 'pending', 'body' => 'معلّق']);
    pcComment($a, ['status' => 'approved', 'parent_id' => $approved->id, 'body' => 'رد معتمَد']);
    pcComment($a, ['status' => 'pending', 'parent_id' => $approved->id, 'body' => 'رد معلّق']);

    $res = $this->getJson(pcUrl($a))->assertOk();

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.body'))->toBe('معتمَد');
    expect($res->json('data.0.replies'))->toHaveCount(1);
    expect($res->json('data.0.replies.0.body'))->toBe('رد معتمَد');
    expect($res->json('data.0'))->not->toHaveKey('author_email');
    expect($res->json('meta.pagination.total'))->toBe(1);
});

// ─── Create comment ─────────────────────────────────────────────────────────

it('creates a pending comment (guest)', function (): void {
    $a = pcArticle();

    $res = $this->postJson(pcUrl($a), pcGuestPayload())->assertOk();

    expect($res->json('data.status'))->toBe('pending');
    $c = Comment::where('commentable_id', $a->id)->whereNull('parent_id')->first();
    expect($c->status->value)->toBe('pending');
    expect($c->body)->toBe('تعليق عام جيّد ومفيد');
});

it('creates a pending comment for an authenticated user (body only, links user_id)', function (): void {
    $a = pcArticle();
    $user = User::factory()->create(['name' => 'عضو مسجّل']);
    Sanctum::actingAs($user);

    // body only — no name/email (the frontend hides them for signed-in users)
    $res = $this->postJson(pcUrl($a), ['body' => 'تعليق عضو مسجّل ومفيد'])->assertOk();

    expect($res->json('data.status'))->toBe('pending');
    $c = Comment::where('commentable_id', $a->id)->whereNull('parent_id')->first();
    expect($c->user_id)->toBe($user->id);   // user_id linked (no 422 — sanctum guard resolved)
    expect($c->author_name)->toBeNull();     // name/email NOT required/stored (from account at display)
    expect($c->author_email)->toBeNull();
    expect($c->status->value)->toBe('pending');
});

// ─── Create reply ─────────────────────────────────────────────────────────────

it('creates a pending reply via parent_id', function (): void {
    $a = pcArticle();
    $parent = pcComment($a, ['status' => 'approved']);

    $res = $this->postJson(pcUrl($a), pcGuestPayload(['parent_id' => $parent->id]))->assertOk();

    expect($res->json('data.status'))->toBe('pending');
    $reply = Comment::where('parent_id', $parent->id)->first();
    expect($reply)->not->toBeNull();
    expect($reply->status->value)->toBe('pending');
});

it('rejects a reply to a non-approved parent (422)', function (): void {
    $a = pcArticle();
    $pendingParent = pcComment($a, ['status' => 'pending']);

    $this->postJson(pcUrl($a), pcGuestPayload(['parent_id' => $pendingParent->id]))
        ->assertStatus(422);
});

// ─── Rate limit ───────────────────────────────────────────────────────────────

it('rate-limits comment submission (comments.submit)', function (): void {
    $a = pcArticle();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(pcUrl($a), pcGuestPayload(['body' => 'تعليق رقم '.$i.' مفيد']))->assertOk();
    }

    $this->postJson(pcUrl($a), pcGuestPayload(['body' => 'تعليق زائد عن الحدّ']))
        ->assertStatus(429);
});

// ─── Gating: Global OFF ─────────────────────────────────────────────────────

it('blocks list + create when GLOBAL comments are disabled', function (): void {
    app(GeneralSettings::class)->fill(['comments_enabled' => false])->save();
    $a = pcArticle();
    pcComment($a, ['status' => 'approved']);

    expect($this->getJson(pcUrl($a))->assertOk()->json('data'))->toHaveCount(0);
    $this->postJson(pcUrl($a), pcGuestPayload())->assertStatus(403);
});

// ─── Gating: Article OFF ────────────────────────────────────────────────────

it('blocks list + create when ARTICLE comments are disabled', function (): void {
    $a = pcArticle(commentsEnabled: false);
    pcComment($a, ['status' => 'approved']);

    expect($this->getJson(pcUrl($a))->assertOk()->json('data'))->toHaveCount(0);
    $this->postJson(pcUrl($a), pcGuestPayload())->assertStatus(403);
});
