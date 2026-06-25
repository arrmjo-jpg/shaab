<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\BroadcastCategory;
use App\Models\MediaAsset;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** بثّ عام بحالة/نوع محدّدين، مع أزمنة منطقية لكل حالة (مرآة سطح الـ API). */
function bpMake(string $status, string $kind = 'live', array $attrs = []): Broadcast
{
    $times = match ($status) {
        'scheduled' => ['scheduled_at' => now()->addHour()],
        'live' => ['started_at' => now()->subMinutes(5)],
        'ended' => ['started_at' => now()->subHour(), 'ended_at' => now()->subMinutes(5)],
        default => [],
    };

    return Broadcast::factory()->create(array_merge([
        'status' => $status,
        'kind' => $kind,
        'is_public' => true,
        'title' => 'بثّ '.uniqid(),
    ], $times, $attrs));
}

it('escapes broadcast content in the JSON-LD so it cannot break out of the script tag (XSS hardening)', function (): void {
    bpMake('live', 'live', ['title' => 'Hack</script><script>alert(1)</script>', 'slug' => 'xss-probe']);

    $html = $this->get('/live/xss-probe')->assertOk()->getContent();

    // The admin-controlled title must NOT break out of any <script> block raw.
    expect($html)->not->toContain('<script>alert(1)');
    // The broadcast DID render with the title HTML-escaped (not a false-positive).
    expect($html)->toContain('Hack&lt;');
});

function bpPublicVideo(array $attrs = []): Video
{
    $asset = MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'external',
        'disk' => 'external',
        'path' => '',
        'filename' => '',
        'original_name' => 'https://youtu.be/'.Str::random(11),
        'mime_type' => 'video/external',
        'extension' => '',
        'size' => 0,
        'checksum' => hash('sha256', Str::random()),
        'provider' => 'youtube',
        'provider_id' => Str::random(11),
        'embed_url' => 'https://www.youtube.com/embed/'.Str::random(11),
        'source_url' => 'https://youtu.be/'.Str::random(11),
        'poster_url' => 'https://img.youtube.com/vi/abc/hqdefault.jpg',
        'visibility' => 'public',
    ]);

    return Video::create(array_merge([
        'title' => 'تسجيل '.uniqid(),
        'locale' => 'ar',
        'status' => 'published',
        'visibility' => 'public',
        'published_at' => now()->subMinute(),
        'media_asset_id' => $asset->id,
        'source_type' => 'youtube',
    ], $attrs));
}

// ─── Listing pages (SSR HTML) ───────────────────────────────────────────────

it('renders the /live listing with live + scheduled sections and excludes hidden states', function (): void {
    bpMake('live', 'live', ['title' => 'بثّ مباشر الآن']);
    bpMake('scheduled', 'live', ['title' => 'بثّ مجدول قادم']);
    bpMake('ended', 'live', ['title' => 'بثّ منتهٍ']);
    bpMake('draft', 'live', ['title' => 'بثّ مسودة']);

    $res = $this->get('/live')->assertOk();

    $res->assertSee('بثّ مباشر الآن');
    $res->assertSee('بثّ مجدول قادم');
    $res->assertSee('مباشر الآن'); // section heading
    $res->assertDontSee('بثّ منتهٍ');
    $res->assertDontSee('بثّ مسودة');
});

it('renders tv/radio directory listings including offline/failed channels', function (string $kind): void {
    bpMake('offline', $kind, ['title' => 'قناة متوقفة']);
    bpMake('failed', $kind, ['title' => 'قناة فاشلة']);
    bpMake('live', $kind, ['title' => 'قناة مباشرة']);

    $res = $this->get('/'.$kind)->assertOk();

    $res->assertSee('قناة متوقفة');
    $res->assertSee('قناة فاشلة');
    $res->assertSee('قناة مباشرة');
})->with(['tv', 'radio']);

it('shows a kind-aware empty state when nothing is listed', function (): void {
    $this->get('/live')->assertOk()->assertSee('لا يوجد محتوى بعد');
});

it('404s an unknown kind segment on the web surface', function (): void {
    $this->get('/podcast')->assertNotFound();
});

// ─── Detail pages (state-driven SSR) ────────────────────────────────────────

it('renders a LIVE detail page with player config data-attributes and the live source', function (): void {
    $b = bpMake('live', 'live', [
        'title' => 'حدث مباشر',
        'source_type' => 'hls',
        'source_url' => 'https://cdn.allowed.test/live.m3u8',
    ]);

    $res = $this->get("/live/{$b->slug}")->assertOk();

    $res->assertSee('data-broadcast-id="'.$b->id.'"', false);
    $res->assertSee('data-status="live"', false);
    $res->assertSee('data-source-type="hls"', false);
    // The live source IS embedded on the playable live page (it's a public stream).
    $res->assertSee('https://cdn.allowed.test/live.m3u8', false);
    $res->assertSee('data-player', false);
});

it('renders a breaking badge for a featured broadcast', function (): void {
    $b = bpMake('live', 'live', ['is_featured' => true]);

    $this->get("/live/{$b->slug}")->assertOk()->assertSee('عاجل');
});

it('renders a SCHEDULED detail page with a countdown and never exposes the source', function (): void {
    $b = bpMake('scheduled', 'live', ['source_url' => 'https://www.youtube.com/watch?v=SECRET12345']);

    $res = $this->get("/live/{$b->slug}")->assertOk();

    $res->assertSee('data-countdown', false);
    $res->assertSee('data-reminder', false);
    $res->assertDontSee('SECRET12345');
    $res->assertDontSee('data-source-url', false);
});

it('renders an ENDED detail page with a VOD CTA when a public recording is linked', function (): void {
    $video = bpPublicVideo(['title' => 'إعادة الحدث']);
    $b = bpMake('ended', 'live', ['vod_video_id' => $video->id, 'source_url' => 'https://www.youtube.com/watch?v=SECRET98765']);

    $res = $this->get("/live/{$b->slug}")->assertOk();

    $res->assertSee('انتهى البثّ');
    $res->assertSee('مشاهدة التسجيل الكامل');
    $res->assertSee($video->canonicalPath(), false);
    $res->assertDontSee('SECRET98765');
});

it('renders OFFLINE/FAILED detail pages with a graceful unavailable state and no source', function (string $status): void {
    $b = bpMake($status, 'tv', ['source_url' => 'https://www.youtube.com/watch?v=SECRET55555']);

    $res = $this->get("/tv/{$b->slug}")->assertOk();

    $res->assertDontSee('SECRET55555');
    $res->assertDontSee('data-source-url', false);
})->with(['offline', 'failed']);

it('routes a broadcast only under its own kind segment on the web surface', function (): void {
    $tv = bpMake('live', 'tv');

    $this->get("/tv/{$tv->slug}")->assertOk();
    $this->get("/live/{$tv->slug}")->assertNotFound();
    $this->get("/radio/{$tv->slug}")->assertNotFound();
});

it('404s draft and archived broadcasts on the web detail surface', function (): void {
    $this->get('/live/'.bpMake('draft')->slug)->assertNotFound();
    $this->get('/live/'.bpMake('archived')->slug)->assertNotFound();
});

// ─── SEO head (SSR-first) ────────────────────────────────────────────────────

it('renders SSR SEO head: canonical, og tags and JSON-LD structured data', function (): void {
    $b = bpMake('live', 'live', ['title' => 'حدث مهم']);

    $res = $this->get("/live/{$b->slug}")->assertOk();

    $res->assertSee('<link rel="canonical"', false);
    $res->assertSee('property="og:title"', false);
    $res->assertSee('application/ld+json', false);
    $res->assertSee('VideoObject', false);
    $res->assertSee('property="og:locale" content="ar_AR"', false);
});

it('renders an AudioObject in JSON-LD for a radio broadcast', function (): void {
    $b = bpMake('live', 'radio', ['source_type' => 'icecast', 'source_url' => 'https://stream.allowed.test/radio']);

    $this->get("/radio/{$b->slug}")->assertOk()->assertSee('AudioObject', false);
});

it('renders engagement counts on the detail page from the SSR metrics', function (): void {
    $cat = BroadcastCategory::create(['name' => 'أخبار', 'is_active' => true]);
    $b = bpMake('live', 'live', ['category_id' => $cat->id]);
    $b->engagementCounter()->create(['likes' => 42, 'dislikes' => 3, 'views' => 100, 'favorites' => 0]);

    $res = $this->get("/live/{$b->slug}")->assertOk();

    $res->assertSee('data-like-count', false);
    $res->assertSee('data-reactions', false);
    $res->assertSee('أخبار');
});
