<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Models\Reel;
use App\Models\ReelUrlHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Cache::flush();
    config(['seo.publisher.logo' => 'https://cdn.test/logo.png', 'seo.publisher.name' => 'AlphaReels']);
});

function rcReadyAsset(): MediaAsset
{
    $uuid = 'rc-'.uniqid();

    return MediaAsset::create([
        'uuid' => $uuid,
        'disk' => 'uploads',
        'path' => "assets/{$uuid}/{$uuid}.mp4",
        'filename' => "{$uuid}.mp4",
        'original_name' => 'reel.mp4',
        'extension' => 'mp4',
        'size' => 2048,
        'mime_type' => 'video/mp4',
        'processing_status' => 'ready',
        'visibility' => 'public',
        'conversions' => [
            'poster' => ['path' => "assets/{$uuid}/poster.jpg", 'width' => 720, 'height' => 1280],
            'hls' => ['master' => "assets/{$uuid}/hls/master.m3u8"],
        ],
    ]);
}

function rcReel(array $attrs = []): Reel
{
    return Reel::create(array_merge([
        'title' => 'ريل '.uniqid(),
        'slug' => 'rc-'.uniqid(),
        'locale' => 'ar',
        'status' => 'published',
        'media_asset_id' => rcReadyAsset()->id,
        'duration_seconds' => 42,
        'published_at' => now()->subDay(),
    ], $attrs))->fresh();
}

// ─── TASK 2: cursor pagination ──────────────────────────────────────────────

it('reels feed supports cursor pagination for mobile infinite scroll', function (): void {
    rcReel();
    rcReel();
    rcReel();

    $res = $this->getJson('/api/v1/ar/reels?paginate=cursor&per_page=2')->assertOk();

    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('meta.cursor.has_more'))->toBeTrue();
    expect($res->json('meta.cursor.next_cursor'))->not->toBeNull();
    expect($res->json('meta'))->not->toHaveKey('pagination');
});

it('reels feed still defaults to offset pagination', function (): void {
    rcReel();

    $res = $this->getJson('/api/v1/ar/reels')->assertOk();

    expect($res->json('meta.pagination.total'))->toBe(1);
});

// ─── TASK 3: SEO excellence (VideoObject + OG + Twitter + hreflang) ──────────

it('reel detail emits VideoObject structured data', function (): void {
    $reel = rcReel(['title' => 'مقطع مميز']);

    $res = $this->getJson("/api/v1/ar/reels/{$reel->slug}")->assertOk();

    $sd = $res->json('data.seo.structured_data');
    expect($sd['@type'])->toBe('VideoObject');
    expect($sd['name'])->toBe('مقطع مميز');
    expect($sd['inLanguage'])->toBe('ar');
    expect($sd['duration'])->toBe('PT42S');
    expect($sd['uploadDate'])->not->toBeNull();
    expect($sd['publisher']['logo']['url'])->toBe('https://cdn.test/logo.png');
});

it('reel detail exposes OpenGraph video + Twitter + canonical + x-default hreflang', function (): void {
    $reel = rcReel();

    $res = $this->getJson("/api/v1/ar/reels/{$reel->slug}")->assertOk();

    expect($res->json('data.seo.og.type'))->toBe('video.other');
    expect($res->json('data.seo.twitter.card'))->toBeIn(['summary', 'summary_large_image']);
    expect($res->json('data.seo.canonical_url'))->toEndWith("/ar/reels/{$reel->id}-{$reel->slug}");
    $locales = collect($res->json('data.seo.hreflang'))->pluck('locale');
    expect($locales)->toContain('x-default');
});

// ─── TASK 4: reels sitemap ──────────────────────────────────────────────────

it('reels sitemap lists published reels', function (): void {
    $reel = rcReel();

    $xml = $this->get('/sitemap-reels-ar.xml')->assertOk()->getContent();

    expect($xml)->toContain($reel->slug);
});

it('sitemap index references the reels sitemap', function (): void {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('sitemap-reels-ar.xml');
});

// ─── TASK 5: reel URL history / 301 redirects ───────────────────────────────

it('301-redirects an old reel slug to the current URL', function (): void {
    $reel = rcReel(['slug' => 'new-reel']);
    ReelUrlHistory::create([
        'reel_id' => $reel->id,
        'locale' => 'ar',
        'old_path' => "/ar/reels/{$reel->id}-old-reel",
        'reason' => 'canonical_change',
    ]);

    $res = $this->getJson('/api/v1/ar/reels/old-reel');

    $res->assertStatus(301);
    expect($res->headers->get('Location'))->toEndWith('/api/v1/ar/reels/new-reel');
});

it('reel redirect endpoint resolves a full old path to a 301', function (): void {
    $reel = rcReel(['slug' => 'renamed']);
    $oldPath = "/ar/reels/{$reel->id}-original";
    ReelUrlHistory::create([
        'reel_id' => $reel->id,
        'locale' => 'ar',
        'old_path' => $oldPath,
        'reason' => 'canonical_change',
    ]);

    $res = $this->getJson('/api/v1/ar/redirects/reels?path='.urlencode($oldPath));

    $res->assertStatus(301);
    expect($res->headers->get('Location'))->toContain("/ar/reels/{$reel->id}-renamed");
});

it('unknown reel slug with no history returns 404', function (): void {
    rcReel(['slug' => 'live-reel']);

    $this->getJson('/api/v1/ar/reels/does-not-exist')->assertStatus(404);
});
