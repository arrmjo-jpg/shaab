<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentDailyStat;
use App\Models\EngagementCounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function aaSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function aaCategory(): Category
{
    return Category::create([
        'name' => 'c-'.uniqid(),
        'slug' => 'cat-'.uniqid(),
        'locale' => 'ar',
        'scope' => 'both',
        'status' => 'active',
    ]);
}

function aaArticle(array $attrs = []): Article
{
    return Article::create(array_merge([
        'author_id' => null,
        'primary_category_id' => aaCategory()->id,
        'type' => 'news',
        'status' => 'published',
        'locale' => 'ar',
        'title' => 'مقال '.uniqid(),
        'slug' => 'article-'.uniqid(),
        'published_at' => now()->subDay(),
    ], $attrs));
}

function aaCounter(Article $article, int $views, int $likes = 0, int $dislikes = 0, int $favorites = 0): void
{
    EngagementCounter::create([
        'engageable_type' => $article->getMorphClass(),
        'engageable_id' => $article->id,
        'views' => $views, 'likes' => $likes, 'dislikes' => $dislikes, 'favorites' => $favorites,
    ]);
}

// ─── Per-article analytics ────────────────────────────────────────────────────

it('returns real per-article analytics (engagement, trend, traffic, performance)', function (): void {
    $token = aaSuperToken();
    $article = aaArticle(['is_featured' => true]);
    $morph = $article->getMorphClass();

    aaCounter($article, views: 200, likes: 20, dislikes: 4, favorites: 8);

    ContentDailyStat::create([
        'engageable_type' => $morph, 'engageable_id' => $article->id, 'day' => now()->toDateString(),
        'views' => 50, 'likes' => 5, 'dislikes' => 0, 'favorites' => 2,
        'views_search' => 30, 'views_direct' => 20,
    ]);

    $res = $this->withToken($token)
        ->getJson("/api/v1/admin/articles/{$article->id}/analytics?range=7d")
        ->assertOk();

    // Engagement (cumulative) + rate + favorites = saves.
    expect($res->json('data.engagement.views'))->toBe(200);
    expect($res->json('data.engagement.favorites'))->toBe(8);
    expect($res->json('data.engagement.engagement_rate'))->toEqual(16.0); // (20+4+8)/200*100

    // Article-specific: type surfaced from enum cast.
    expect($res->json('data.entity.type'))->toBe('news');

    // Trend (forward-only) + traffic.
    expect($res->json('data.trend.window.range'))->toBe('7d');
    expect($res->json('data.trend.points'))->toHaveCount(7);
    expect($res->json('data.trend.totals.views'))->toBe(50);
    expect($res->json('data.traffic.total'))->toBe(50);
    expect($res->json('data.traffic.channels.search'))->toBe(30);

    // Performance: weighted trending score (200·1 + 20·4 + 8·6 − 4·2 = 320).
    expect($res->json('data.performance.trending_score'))->toBe(320);
    expect($res->json('data.performance.baseline.avg_views'))->toBeInt();

    expect($res->json('data.publishing.is_featured'))->toBeTrue();
});

// ─── Fleet analytics ───────────────────────────────────────────────────────────

it('returns fleet article analytics (leaderboard ordered, language, featured impact, publish-time)', function (): void {
    $token = aaSuperToken();

    $top = aaArticle(['is_featured' => true]);
    aaCounter($top, views: 1000, likes: 50);
    $low = aaArticle();
    aaCounter($low, views: 10);

    $res = $this->withToken($token)
        ->getJson('/api/v1/admin/articles/analytics')
        ->assertOk();

    expect($res->json('data.engagement.views'))->toBe(1010);

    // Leaderboard ordered by weighted score (top article first).
    expect($res->json('data.top_performers.0.id'))->toBe($top->id);

    // Language segmentation (both ar).
    expect($res->json('data.language.0.locale'))->toBe('ar');
    expect($res->json('data.language.0.articles'))->toBe(2);

    // Featured impact: featured avg >> regular avg → positive lift.
    expect($res->json('data.featured_impact.featured.articles'))->toBe(1);
    expect($res->json('data.featured_impact.regular.articles'))->toBe(1);
    expect($res->json('data.featured_impact.lift_pct'))->toBeGreaterThan(0);

    // Publish-time = 24 hourly buckets.
    expect($res->json('data.publish_time'))->toHaveCount(24);
});

// ─── Permissions ───────────────────────────────────────────────────────────────

it('requires articles.view for both article analytics surfaces', function (): void {
    $article = aaArticle();
    $token = User::factory()->create()->createToken('admin', ['admin'])->plainTextToken; // no roles

    $this->withToken($token)->getJson('/api/v1/admin/articles/analytics')->assertStatus(403);
    $this->withToken($token)->getJson("/api/v1/admin/articles/{$article->id}/analytics")->assertStatus(403);
});
