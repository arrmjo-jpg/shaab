<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pubEngArticle(string $status = 'published'): Article
{
    $cat = Category::create([
        'name' => 'ت'.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'news',
    ]);

    return Article::create([
        'title' => 'خبر '.uniqid(), 'locale' => 'ar', 'type' => 'news', 'status' => $status,
        'primary_category_id' => $cat->id, 'author_id' => User::factory()->create()->id,
        'content_json' => ['type' => 'doc', 'content' => []], 'content' => '<p>x</p>',
        'published_at' => $status === 'published' ? now() : null,
    ]);
}

/** Guest actor identity for the hybrid strategy. */
function guestHeaders(string $device = 'device-pub-1'): array
{
    return ['X-Client-Id' => $device];
}

// ─── React (like / dislike / replace) ────────────────────────────────────────

it('records a like via the public react endpoint', function (): void {
    $a = pubEngArticle();

    $res = $this->withHeaders(guestHeaders())
        ->postJson("/api/v1/engagement/article/{$a->id}/react", ['reaction' => 'like'])
        ->assertOk();

    expect($res->json('data.reaction'))->toBe('like');
    expect($res->json('data.metrics.likes'))->toBe(1);
    expect($res->json('data.favorited'))->toBeFalse();
});

it('replaces a like with a dislike for the same actor', function (): void {
    $a = pubEngArticle();

    $this->withHeaders(guestHeaders())
        ->postJson("/api/v1/engagement/article/{$a->id}/react", ['reaction' => 'like'])->assertOk();

    $res = $this->withHeaders(guestHeaders())
        ->postJson("/api/v1/engagement/article/{$a->id}/react", ['reaction' => 'dislike'])
        ->assertOk();

    expect($res->json('data.reaction'))->toBe('dislike');
    expect($res->json('data.metrics.likes'))->toBe(0);
    expect($res->json('data.metrics.dislikes'))->toBe(1);
});

it('rejects an invalid reaction value', function (): void {
    $a = pubEngArticle();

    $this->withHeaders(guestHeaders())
        ->postJson("/api/v1/engagement/article/{$a->id}/react", ['reaction' => 'favorite'])
        ->assertStatus(422);
});

// ─── Remove reaction ─────────────────────────────────────────────────────────

it('removes a reaction via the public endpoint', function (): void {
    $a = pubEngArticle();

    $this->withHeaders(guestHeaders())
        ->postJson("/api/v1/engagement/article/{$a->id}/react", ['reaction' => 'like'])->assertOk();

    $res = $this->withHeaders(guestHeaders())
        ->deleteJson("/api/v1/engagement/article/{$a->id}/react")
        ->assertOk();

    expect($res->json('data.reaction'))->toBeNull();
    expect($res->json('data.metrics.likes'))->toBe(0);
});

// ─── Favorite toggle ─────────────────────────────────────────────────────────

it('toggles favorite on and off via the public endpoint', function (): void {
    $a = pubEngArticle();

    $res = $this->withHeaders(guestHeaders())
        ->postJson("/api/v1/engagement/article/{$a->id}/favorite")
        ->assertOk();
    expect($res->json('data.favorited'))->toBeTrue();
    expect($res->json('data.metrics.favorites'))->toBe(1);

    $res = $this->withHeaders(guestHeaders())
        ->postJson("/api/v1/engagement/article/{$a->id}/favorite")
        ->assertOk();
    expect($res->json('data.favorited'))->toBeFalse();
    expect($res->json('data.metrics.favorites'))->toBe(0);
});

// ─── Fetch state ─────────────────────────────────────────────────────────────

it('returns the current state for the requesting actor', function (): void {
    $a = pubEngArticle();

    $this->withHeaders(guestHeaders())
        ->postJson("/api/v1/engagement/article/{$a->id}/react", ['reaction' => 'like'])->assertOk();

    $res = $this->withHeaders(guestHeaders())
        ->getJson("/api/v1/engagement/article/{$a->id}")
        ->assertOk();

    expect($res->json('data.reaction'))->toBe('like');
    expect($res->json('data.favorited'))->toBeFalse();
    expect($res->json('data.metrics'))->toHaveKeys(['views', 'likes', 'dislikes', 'favorites']);
});

it('scopes state to the actor — a different device sees no reaction', function (): void {
    $a = pubEngArticle();

    $this->withHeaders(guestHeaders('device-A'))
        ->postJson("/api/v1/engagement/article/{$a->id}/react", ['reaction' => 'like'])->assertOk();

    $res = $this->withHeaders(guestHeaders('device-B'))
        ->getJson("/api/v1/engagement/article/{$a->id}")
        ->assertOk();

    expect($res->json('data.reaction'))->toBeNull();
    expect($res->json('data.metrics.likes'))->toBe(1); // aggregate is shared
});

// ─── Guards ──────────────────────────────────────────────────────────────────

it('returns 422 for an unsupported engageable type', function (): void {
    $this->withHeaders(guestHeaders())
        ->getJson('/api/v1/engagement/unicorn/1')
        ->assertStatus(422);
});

it('returns 404 for a missing target', function (): void {
    $this->withHeaders(guestHeaders())
        ->getJson('/api/v1/engagement/article/999999')
        ->assertStatus(404);
});

it('returns 404 for an unpublished article', function (): void {
    $a = pubEngArticle('draft');

    $this->withHeaders(guestHeaders())
        ->getJson("/api/v1/engagement/article/{$a->id}")
        ->assertStatus(404);
});
