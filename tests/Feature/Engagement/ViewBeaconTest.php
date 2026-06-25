<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\EngagementCounter;
use App\Models\MediaAsset;
use App\Models\Reel;
use App\Models\User;
use App\Models\Video;
use App\Support\Engagement\EngagementBeaconToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── Self-contained published/viewable targets (unique helper names) ──────────

function beaconVideo(string $status = 'published'): Video
{
    $asset = MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'external', 'disk' => 'external', 'path' => '',
        'filename' => '', 'original_name' => 'x', 'mime_type' => 'video/external', 'extension' => '',
        'size' => 0, 'checksum' => hash('sha256', Str::random()), 'provider' => 'youtube',
        'provider_id' => Str::random(11), 'embed_url' => 'https://www.youtube.com/embed/'.Str::random(11),
        'source_url' => 'https://youtu.be/'.Str::random(11), 'poster_url' => 'https://img.youtube.com/x.jpg',
        'visibility' => 'public',
    ]);

    return Video::create([
        'title' => 'فيديو '.uniqid(), 'locale' => 'ar', 'status' => $status, 'visibility' => 'public',
        'published_at' => $status === 'published' ? now()->subMinute() : null,
        'media_asset_id' => $asset->id, 'source_type' => 'youtube',
    ]);
}

function beaconReel(): Reel
{
    return Reel::create([
        'title' => 'ريل '.uniqid(), 'locale' => 'ar', 'status' => 'published', 'published_at' => now()->subMinute(),
    ]);
}

function beaconArticle(): Article
{
    $cat = Category::create(['name' => 'ت'.uniqid(), 'locale' => 'ar', 'status' => 'active', 'scope' => 'news']);

    return Article::create([
        'title' => 'خبر '.uniqid(), 'locale' => 'ar', 'type' => 'news', 'status' => 'published',
        'primary_category_id' => $cat->id, 'author_id' => User::factory()->create()->id,
        'content_json' => ['type' => 'doc', 'content' => []], 'content' => '<p>x</p>', 'published_at' => now(),
    ]);
}

function beaconViews(string $morphClass, int $id): int
{
    return (int) (EngagementCounter::query()
        ->where('engageable_type', $morphClass)
        ->where('engageable_id', $id)
        ->value('views') ?? 0);
}

// ─── Records a view for every supported type ──────────────────────────────────

it('records a view via the beacon for a video, reel and article', function (): void {
    $video = beaconVideo();
    $reel = beaconReel();
    $article = beaconArticle();

    $this->withHeaders(['X-Client-Id' => 'c-v'])
        ->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => EngagementBeaconToken::issue('video', $video->id)])
        ->assertOk()->assertJsonPath('data.accepted', true);
    $this->withHeaders(['X-Client-Id' => 'c-r'])
        ->postJson("/api/v1/engagement/reel/{$reel->id}/view", ['token' => EngagementBeaconToken::issue('reel', $reel->id)])
        ->assertOk();
    $this->withHeaders(['X-Client-Id' => 'c-a'])
        ->postJson("/api/v1/engagement/article/{$article->id}/view", ['token' => EngagementBeaconToken::issue('article', $article->id)])
        ->assertOk();

    expect(beaconViews((new Video)->getMorphClass(), $video->id))->toBe(1);
    expect(beaconViews((new Reel)->getMorphClass(), $reel->id))->toBe(1);
    expect(beaconViews((new Article)->getMorphClass(), $article->id))->toBe(1);
});

// ─── Signed-token validation (abuse resistance) ───────────────────────────────

it('rejects a beacon with a missing token (422 validation)', function (): void {
    $video = beaconVideo();

    $this->withHeaders(['X-Client-Id' => 'c'])
        ->postJson("/api/v1/engagement/video/{$video->id}/view", [])
        ->assertStatus(422)->assertJsonValidationErrors('token');
});

it('rejects a tampered token', function (): void {
    $video = beaconVideo();
    $token = EngagementBeaconToken::issue('video', $video->id);

    $this->withHeaders(['X-Client-Id' => 'c'])
        ->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => $token.'x'])
        ->assertStatus(422);
    expect(beaconViews((new Video)->getMorphClass(), $video->id))->toBe(0);
});

it('rejects a token issued for a different id or type', function (): void {
    $video = beaconVideo();
    $other = beaconVideo();

    // رمز هدف آخر.
    $this->withHeaders(['X-Client-Id' => 'c'])
        ->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => EngagementBeaconToken::issue('video', $other->id)])
        ->assertStatus(422);

    // رمز نوع آخر (reel) على فيديو.
    $this->withHeaders(['X-Client-Id' => 'c2'])
        ->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => EngagementBeaconToken::issue('reel', $video->id)])
        ->assertStatus(422);
});

it('rejects an expired token', function (): void {
    config(['performance.view_beacon.ttl' => 1]);
    $video = beaconVideo();
    $token = EngagementBeaconToken::issue('video', $video->id);

    $this->travel(5)->seconds();

    $this->withHeaders(['X-Client-Id' => 'c'])
        ->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => $token])
        ->assertStatus(422);
});

it('returns 422 for an unsupported type and 404 for a non-viewable target', function (): void {
    $this->postJson('/api/v1/engagement/unicorn/1/view', ['token' => 'x'])->assertStatus(422);

    $draft = beaconVideo('draft'); // غير قابل للعرض
    $this->withHeaders(['X-Client-Id' => 'c'])
        ->postJson("/api/v1/engagement/video/{$draft->id}/view", ['token' => EngagementBeaconToken::issue('video', $draft->id)])
        ->assertStatus(404);
});

// ─── Dedup + bot + no-store + rate limit ──────────────────────────────────────

it('dedups repeated beacons from the same actor within the window', function (): void {
    $video = beaconVideo();
    $token = EngagementBeaconToken::issue('video', $video->id);
    $headers = ['X-Client-Id' => 'same-device'];

    $this->withHeaders($headers)->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => $token])->assertOk();
    $this->withHeaders($headers)->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => $token])->assertOk();

    expect(beaconViews((new Video)->getMorphClass(), $video->id))->toBe(1);
});

it('does not count a bot view and never caches the beacon response', function (): void {
    $video = beaconVideo();
    $token = EngagementBeaconToken::issue('video', $video->id);

    $res = $this->withHeaders(['User-Agent' => 'Googlebot/2.1', 'X-Client-Id' => 'bot-1'])
        ->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => $token])->assertOk();

    expect($res->headers->get('Cache-Control'))->toContain('no-store');
    expect(beaconViews((new Video)->getMorphClass(), $video->id))->toBe(0);
});

it('throttles excessive beacons per client', function (): void {
    config(['performance.view_beacon.rate_limit' => 3]);
    $video = beaconVideo();
    $token = EngagementBeaconToken::issue('video', $video->id);
    $headers = ['X-Client-Id' => 'flooder-'.uniqid()];

    for ($i = 0; $i < 3; $i++) {
        $this->withHeaders($headers)->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => $token])->assertOk();
    }
    $this->withHeaders($headers)->postJson("/api/v1/engagement/video/{$video->id}/view", ['token' => $token])->assertStatus(429);
});

// ─── Detail responses issue a verifiable signed token ─────────────────────────

it('exposes a signed view_token in detail meta for all three types', function (): void {
    $video = beaconVideo();
    $reel = beaconReel();
    $article = beaconArticle();

    $vt = $this->getJson("/api/v1/ar/videos/{$video->slug}")->assertOk()->json('meta.view_token');
    $rt = $this->getJson("/api/v1/ar/reels/{$reel->slug}")->assertOk()->json('meta.view_token');
    $at = $this->getJson("/api/v1/ar/articles/{$article->slug}")->assertOk()->json('meta.view_token');

    expect(EngagementBeaconToken::verify((string) $vt, 'video', $video->id))->toBeTrue();
    expect(EngagementBeaconToken::verify((string) $rt, 'reel', $reel->id))->toBeTrue();
    expect(EngagementBeaconToken::verify((string) $at, 'article', $article->id))->toBeTrue();
});

it('no longer counts a view from the cacheable detail GET (beacon is the source)', function (): void {
    $video = beaconVideo();

    $this->getJson("/api/v1/ar/videos/{$video->slug}")->assertOk();
    $this->getJson("/api/v1/ar/videos/{$video->slug}")->assertOk();

    // طلب التفاصيل لم يَعُد يحتسب — المنارة وحدها تفعل.
    expect(beaconViews((new Video)->getMorphClass(), $video->id))->toBe(0);
});
