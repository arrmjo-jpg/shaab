<?php

declare(strict_types=1);

use App\Models\ContentDailyStat;
use App\Models\EngagementCounter;
use App\Models\MediaAsset;
use App\Models\Reel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function raSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function raAsset(): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'file', 'disk' => 'uploads',
        'path' => 'assets/'.Str::uuid().'/v.mp4', 'filename' => 'v.mp4', 'original_name' => 'v.mp4',
        'mime_type' => 'video/mp4', 'extension' => 'mp4', 'size' => 1000,
        'checksum' => hash('sha256', Str::random()), 'processing_status' => 'ready', 'visibility' => 'public',
    ]);
}

function raReel(array $attrs = []): Reel
{
    return Reel::create(array_merge([
        'title' => 'ريل '.uniqid(),
        'locale' => 'ar',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'media_asset_id' => raAsset()->id,
    ], $attrs));
}

function raCounter(Reel $reel, int $views, int $likes = 0, int $dislikes = 0, int $favorites = 0): void
{
    EngagementCounter::create([
        'engageable_type' => $reel->getMorphClass(),
        'engageable_id' => $reel->id,
        'views' => $views, 'likes' => $likes, 'dislikes' => $dislikes, 'favorites' => $favorites,
    ]);
}

// ─── Per-reel analytics ──────────────────────────────────────────────────────

it('returns real per-reel analytics (engagement, trend, traffic, performance) + honest deferred flags', function (): void {
    $token = raSuperToken();
    $reel = raReel(['is_featured' => true]);
    $morph = $reel->getMorphClass();

    raCounter($reel, views: 200, likes: 20, dislikes: 4, favorites: 8);

    ContentDailyStat::create([
        'engageable_type' => $morph, 'engageable_id' => $reel->id, 'day' => now()->toDateString(),
        'views' => 50, 'likes' => 5, 'dislikes' => 0, 'favorites' => 2,
        'views_search' => 30, 'views_direct' => 20,
    ]);

    $res = $this->withToken($token)
        ->getJson("/api/v1/admin/reels/{$reel->id}/analytics?range=7d")
        ->assertOk();

    // Engagement (cumulative) + rate + favorites = saves.
    expect($res->json('data.engagement.views'))->toBe(200);
    expect($res->json('data.engagement.favorites'))->toBe(8);
    expect($res->json('data.engagement.engagement_rate'))->toEqual(16.0); // (20+4+8)/200*100

    // Trend (forward-only) + traffic.
    expect($res->json('data.trend.window.range'))->toBe('7d');
    expect($res->json('data.trend.points'))->toHaveCount(7);
    expect($res->json('data.trend.totals.views'))->toBe(50);
    expect($res->json('data.traffic.total'))->toBe(50);
    expect($res->json('data.traffic.channels.search'))->toBe(30);

    // Performance: weighted trending score (200·1 + 20·4 + 8·6 − 4·2 = 320).
    expect($res->json('data.performance.trending_score'))->toBe(320);
    expect($res->json('data.performance.baseline.avg_views'))->toBeInt();

    // Deferred short-form metrics — honestly flagged, never faked.
    expect($res->json('data.deferred.watch.available'))->toBeFalse();
    expect($res->json('data.deferred.discovery.available'))->toBeFalse();
    expect($res->json('data.publishing.is_featured'))->toBeTrue();
});

// ─── Fleet analytics ─────────────────────────────────────────────────────────

it('returns fleet reel analytics (leaderboard ordered, language, featured impact, publish-time)', function (): void {
    $token = raSuperToken();

    $top = raReel(['is_featured' => true]);
    raCounter($top, views: 1000, likes: 50);
    $low = raReel();
    raCounter($low, views: 10);

    $res = $this->withToken($token)
        ->getJson('/api/v1/admin/reels/analytics')
        ->assertOk();

    expect($res->json('data.engagement.views'))->toBe(1010);

    // Leaderboard ordered by weighted score (top reel first).
    expect($res->json('data.top_performers.0.id'))->toBe($top->id);

    // Language segmentation (both ar).
    expect($res->json('data.language.0.locale'))->toBe('ar');
    expect($res->json('data.language.0.reels'))->toBe(2);

    // Featured impact: featured avg >> regular avg → positive lift.
    expect($res->json('data.featured_impact.featured.reels'))->toBe(1);
    expect($res->json('data.featured_impact.regular.reels'))->toBe(1);
    expect($res->json('data.featured_impact.lift_pct'))->toBeGreaterThan(0);

    // Publish-time = 24 hourly buckets.
    expect($res->json('data.publish_time'))->toHaveCount(24);
});

// ─── Permissions ─────────────────────────────────────────────────────────────

it('requires reels.view for both reel analytics surfaces', function (): void {
    $reel = raReel();
    $token = User::factory()->create()->createToken('admin', ['admin'])->plainTextToken; // no roles

    $this->withToken($token)->getJson('/api/v1/admin/reels/analytics')->assertStatus(403);
    $this->withToken($token)->getJson("/api/v1/admin/reels/{$reel->id}/analytics")->assertStatus(403);
});
