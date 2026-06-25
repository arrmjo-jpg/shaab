<?php

declare(strict_types=1);

use App\Enums\EngagementType;
use App\Models\Article;
use App\Models\Category;
use App\Models\Engagement;
use App\Models\User;
use App\Support\Engagement\EngagementActor;
use App\Support\Engagement\EngagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function engArticle(): Article
{
    $cat = Category::create([
        'name' => 'ت'.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'news',
    ]);

    return Article::create([
        'title' => 'خبر '.uniqid(), 'locale' => 'ar', 'type' => 'news', 'status' => 'published',
        'primary_category_id' => $cat->id, 'author_id' => User::factory()->create()->id,
        'content_json' => ['type' => 'doc', 'content' => []], 'content' => '<p>x</p>',
        'published_at' => now(),
    ]);
}

function svc(): EngagementService
{
    return app(EngagementService::class);
}

// ─── Reactions ─────────────────────────────────────────────────────────────

it('records a like then replaces it with a dislike', function (): void {
    $a = engArticle();
    $actor = EngagementActor::user(User::factory()->create()->id);

    $s = svc()->react($a, $actor, EngagementType::Like);
    expect($s['reaction'])->toBe('like');
    expect($s['metrics']['likes'])->toBe(1);

    $s = svc()->react($a, $actor, EngagementType::Dislike);
    expect($s['reaction'])->toBe('dislike');
    expect($s['metrics']['likes'])->toBe(0);
    expect($s['metrics']['dislikes'])->toBe(1);
});

it('toggles a like off when repeated', function (): void {
    $a = engArticle();
    $actor = EngagementActor::guest('device-1');

    svc()->react($a, $actor, EngagementType::Like);
    $s = svc()->react($a, $actor, EngagementType::Like);

    expect($s['reaction'])->toBeNull();
    expect($s['metrics']['likes'])->toBe(0);
});

it('enforces a single reaction row per actor', function (): void {
    $a = engArticle();
    $actor = EngagementActor::user(User::factory()->create()->id);

    svc()->react($a, $actor, EngagementType::Like);
    svc()->react($a, $actor, EngagementType::Dislike);
    svc()->react($a, $actor, EngagementType::Like);

    expect(
        Engagement::where('engageable_id', $a->id)
            ->whereIn('type', ['like', 'dislike'])->count()
    )->toBe(1);
});

// ─── Favorites ───────────────────────────────────────────────────────────────

it('toggles favorite independently of reactions', function (): void {
    $a = engArticle();
    $actor = EngagementActor::guest('d2');

    svc()->react($a, $actor, EngagementType::Like);
    $s = svc()->toggleFavorite($a, $actor);
    expect($s['favorited'])->toBeTrue();
    expect($s['reaction'])->toBe('like'); // unaffected
    expect($s['metrics']['favorites'])->toBe(1);

    $s = svc()->toggleFavorite($a, $actor);
    expect($s['favorited'])->toBeFalse();
    expect($s['metrics']['favorites'])->toBe(0);
});

// ─── Views ─────────────────────────────────────────────────────────────────────

it('counts a view once within the dedup window', function (): void {
    Cache::flush();
    $a = engArticle();
    $actor = EngagementActor::guest('viewer-1');

    svc()->recordView($a, $actor);
    svc()->recordView($a, $actor); // deduped
    expect(svc()->metrics($a)['views'])->toBe(1);

    svc()->recordView($a, EngagementActor::guest('viewer-2'));
    expect(svc()->metrics($a)['views'])->toBe(2);
});

// ─── Admin metrics exposure ────────────────────────────────────────────────────

it('exposes engagement metrics in the admin article list', function (): void {
    seedRoles();
    $u = User::factory()->create();
    $u->assignRole('super_admin');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $a = engArticle();
    svc()->react($a, EngagementActor::guest('x'), EngagementType::Like);

    $res = $this->withToken($token)->getJson('/api/v1/admin/articles')->assertOk();
    $row = collect($res->json('data'))->firstWhere('id', $a->id);

    expect($row['metrics']['likes'])->toBe(1);
    expect($row['metrics'])->toHaveKeys(['views', 'likes', 'dislikes', 'favorites']);
});
