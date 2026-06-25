<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentDailyStat;
use App\Models\EngagementCounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function saSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function saArticle(): Article
{
    $cat = Category::create([
        'name' => 'c-'.uniqid(), 'slug' => 'cat-'.uniqid(),
        'locale' => 'ar', 'scope' => 'both', 'status' => 'active',
    ]);

    return Article::create([
        'primary_category_id' => $cat->id, 'type' => 'news', 'status' => 'published',
        'locale' => 'ar', 'title' => 'مقال '.uniqid(), 'slug' => 'a-'.Str::random(8),
        'published_at' => now()->subDay(),
    ]);
}

function saCounter(string $morph, int $id, int $views, int $likes, int $favorites): void
{
    EngagementCounter::create([
        'engageable_type' => $morph, 'engageable_id' => $id,
        'views' => $views, 'likes' => $likes, 'dislikes' => 0, 'favorites' => $favorites,
    ]);
}

// ─── KPIs + inventory + trend + top content + channels from real sources ────

it('aggregates site-wide KPIs, inventory, trend, top content, and channels', function (): void {
    $token = saSuperToken();

    // One published news article (the only article row) + its engagement counter.
    $article = saArticle();
    saCounter('App\Models\Article', $article->id, views: 100, likes: 10, favorites: 5);
    saCounter('App\Models\Reel', 1, views: 50, likes: 4, favorites: 3);
    saCounter('App\Models\Video', 1, views: 20, likes: 1, favorites: 2);

    // Trend + channels — today's content stats for the article.
    ContentDailyStat::create([
        'engageable_type' => 'App\Models\Article', 'engageable_id' => $article->id,
        'day' => now()->toDateString(), 'views' => 30,
        'views_direct' => 12, 'views_search' => 18,
    ]);

    $res = $this->withToken($token)->getJson('/api/v1/admin/dashboard')->assertOk();

    // KPIs (cumulative SUM).
    expect($res->json('data.engagement.views'))->toBe(170);   // 100+50+20
    expect($res->json('data.engagement.likes'))->toBe(15);    // 10+4+1
    expect($res->json('data.engagement.favorites'))->toBe(10); // 5+3+2

    // Inventory.
    expect($res->json('data.inventory.articles'))->toBe(1);
    expect($res->json('data.inventory.reels'))->toBe(0);

    // Trend (30 days, today = 30 views).
    expect($res->json('data.trend'))->toHaveCount(30);
    expect(collect($res->json('data.trend'))->last()['views'])->toBe(30);

    // Top content — score = views·1 + likes·4 + favorites·6 − dislikes·2 = 170.
    expect($res->json('data.top.articles'))->toHaveCount(1);
    expect($res->json('data.top.articles.0.id'))->toBe($article->id);
    expect($res->json('data.top.articles.0.score'))->toBe(170);
    expect($res->json('data.top.news'))->toHaveCount(1);          // type=news filter
    expect($res->json('data.top.news.0.id'))->toBe($article->id);
    expect($res->json('data.top.reels'))->toBe([]);               // no reel rows
    expect($res->json('data.top.videos'))->toBe([]);              // no video rows

    // Channels (5 buckets, 30-day window).
    expect($res->json('data.channels.direct'))->toBe(12);
    expect($res->json('data.channels.search'))->toBe(18);
    expect($res->json('data.channels.internal'))->toBe(0);
    expect($res->json('data.channels.social'))->toBe(0);
    expect($res->json('data.channels.referral'))->toBe(0);

    // Ads + polls wired (queries execute, integer keys present).
    expect($res->json('data.ads.impressions'))->toBeInt();
    expect($res->json('data.ads.clicks'))->toBeInt();
    expect($res->json('data.polls.votes'))->toBeInt();
});

// ─── Permission ────────────────────────────────────────────────────────────

it('requires analytics.view (403 without it)', function (): void {
    $token = User::factory()->create()->createToken('admin', ['admin'])->plainTextToken; // no roles

    $this->withToken($token)->getJson('/api/v1/admin/dashboard')->assertStatus(403);
});
