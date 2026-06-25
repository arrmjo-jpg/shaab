<?php

declare(strict_types=1);

use App\Actions\Admin\Broadcast\EndBroadcastAction;
use App\Models\Broadcast;
use App\Models\BroadcastCategory;
use App\Models\MediaAsset;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── Helpers (أسماء فريدة عالمياً عبر مجموعة Pest) ────────────────────────────

/** بثّ عام بحالة/نوع محدّدين، مع أزمنة منطقية لكل حالة. */
function pbMake(string $status, string $kind = 'live', array $attrs = []): Broadcast
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

/** فيديو عام قابل للتشغيل (أصل خارجي يوتيوب) — لاختبار ربط VOD. */
function pbPublicVideo(array $attrs = []): Video
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

// ─── List visibility (publiclyListed = scheduled|live, by kind) ────────────────

it('lists only public live/scheduled broadcasts of the kind — excludes ended/offline/failed/draft/archived/other kinds', function (): void {
    pbMake('live', 'live', ['title' => 'مباشر']);
    pbMake('scheduled', 'live', ['title' => 'مجدول']);
    pbMake('ended', 'live', ['title' => 'منتهٍ']);
    pbMake('offline', 'live', ['title' => 'متوقف']);
    pbMake('failed', 'live', ['title' => 'فاشل']);
    pbMake('draft', 'live', ['title' => 'مسودة']);
    pbMake('archived', 'live', ['title' => 'مؤرشف']);
    pbMake('live', 'live', ['title' => 'مخفي', 'is_public' => false]);
    pbMake('live', 'tv', ['title' => 'قناة']); // نوع آخر

    $titles = collect($this->getJson('/api/v1/live')->assertOk()->json('data'))->pluck('title');

    expect($titles)->toContain('مباشر');
    expect($titles)->toContain('مجدول');
    foreach (['منتهٍ', 'متوقف', 'فاشل', 'مسودة', 'مؤرشف', 'مخفي', 'قناة'] as $hidden) {
        expect($titles)->not->toContain($hidden);
    }
});

it('keeps tv/radio directory listings visible through offline/failed but still hides draft/archived/ended', function (string $kind): void {
    pbMake('live', $kind, ['title' => 'مباشر']);
    pbMake('scheduled', $kind, ['title' => 'مجدول']);
    pbMake('offline', $kind, ['title' => 'متوقف']);
    pbMake('failed', $kind, ['title' => 'فاشل']);
    pbMake('ended', $kind, ['title' => 'منتهٍ']);
    pbMake('draft', $kind, ['title' => 'مسودة']);
    pbMake('archived', $kind, ['title' => 'مؤرشف']);

    $titles = collect($this->getJson("/api/v1/{$kind}")->assertOk()->json('data'))->pluck('title');

    foreach (['مباشر', 'مجدول', 'متوقف', 'فاشل'] as $shown) {
        expect($titles)->toContain($shown);
    }
    foreach (['منتهٍ', 'مسودة', 'مؤرشف'] as $hidden) {
        expect($titles)->not->toContain($hidden);
    }
})->with(['tv', 'radio']);

it('paginates with offset meta by default and cursor meta on demand', function (): void {
    pbMake('live');
    pbMake('live');
    pbMake('live');

    $offset = $this->getJson('/api/v1/live?per_page=2')->assertOk();
    expect($offset->json('meta.pagination.per_page'))->toBe(2);
    expect($offset->json('meta.pagination.total'))->toBe(3);
    expect($offset->json('data'))->toHaveCount(2);

    $cursor = $this->getJson('/api/v1/live?per_page=2&paginate=cursor')->assertOk();
    expect($cursor->json('meta.cursor.has_more'))->toBeTrue();
    expect($cursor->json('meta.cursor.next_cursor'))->not->toBeNull();
    expect($cursor->json('data'))->toHaveCount(2);
});

it('filters a kind feed by category slug', function (): void {
    $cat = BroadcastCategory::create(['name' => 'أخبار', 'is_active' => true]);

    pbMake('live', 'live', ['title' => 'داخل التصنيف', 'category_id' => $cat->id]);
    pbMake('live', 'live', ['title' => 'خارج التصنيف']);

    $res = $this->getJson("/api/v1/live?filter[category]={$cat->slug}")->assertOk();

    expect(collect($res->json('data'))->pluck('title')->all())->toBe(['داخل التصنيف']);
});

// ─── Detail by slug + kind routing ─────────────────────────────────────────────

it('shows a public broadcast by slug with sanitized seo + playback and no internal leak', function (): void {
    $b = pbMake('live', 'live', ['title' => 'بثّ مهم']);

    $res = $this->getJson("/api/v1/live/{$b->slug}")->assertOk();

    expect($res->json('data.slug'))->toBe($b->slug);
    expect($res->json('data.canonical_path'))->toBe("/live/{$b->slug}");
    expect($res->json('data'))->toHaveKeys(['seo', 'playback']);
    expect($res->json('data.playback.state'))->toBe('live');

    // لا تسريب لحقول داخلية/إدارية/صحّية.
    $data = $res->json('data');
    foreach (['source_url', 'is_public', 'meta', 'last_health_status', 'last_health_message', 'health', 'created_by'] as $leak) {
        expect($data)->not->toHaveKey($leak);
    }
});

it('routes a broadcast only under its own kind segment', function (): void {
    $tv = pbMake('live', 'tv', ['title' => 'قناة تلفاز']);

    $this->getJson("/api/v1/tv/{$tv->slug}")->assertOk();
    $this->getJson("/api/v1/live/{$tv->slug}")->assertStatus(404);
    $this->getJson("/api/v1/radio/{$tv->slug}")->assertStatus(404);
});

it('404s draft and archived broadcasts on the public detail surface', function (): void {
    $draft = pbMake('draft', 'live', ['title' => 'مسودة']);
    $archived = pbMake('archived', 'live', ['title' => 'مؤرشف']);

    $this->getJson("/api/v1/live/{$draft->slug}")->assertStatus(404);
    $this->getJson("/api/v1/live/{$archived->slug}")->assertStatus(404);
});

it('404s an unknown kind segment', function (): void {
    $this->getJson('/api/v1/podcast')->assertStatus(404);
    $this->getJson('/api/v1/podcast/anything')->assertStatus(404);
});

// ─── Per-status playback contract (product-safe FAILED/OFFLINE/ENDED) ───────────

it('exposes the live source only while live', function (): void {
    $b = pbMake('live', 'live', ['source_type' => 'hls', 'source_url' => 'https://cdn.allowed.test/live.m3u8']);

    $res = $this->getJson("/api/v1/live/{$b->slug}")->assertOk();

    expect($res->json('data.playback.source.type'))->toBe('hls');
    expect($res->json('data.playback.source.url'))->toBe('https://cdn.allowed.test/live.m3u8');
});

it('returns an absolute starts_at for a scheduled broadcast and never the source', function (): void {
    $b = pbMake('scheduled', 'live', ['source_url' => 'https://www.youtube.com/watch?v=SECRET12345']);

    $res = $this->getJson("/api/v1/live/{$b->slug}")->assertOk();

    expect($res->json('data.playback.state'))->toBe('upcoming');
    expect($res->json('data.playback.starts_at'))->not->toBeNull();
    expect($res->json('data.playback.source'))->toBeNull();
    expect($res->getContent())->not->toContain('SECRET12345');
});

it('serves ended/offline/failed pages product-safely without ever exposing the source', function (string $status): void {
    $b = pbMake($status, 'live', ['source_url' => 'https://www.youtube.com/watch?v=SECRET98765']);

    $res = $this->getJson("/api/v1/live/{$b->slug}")->assertOk();

    expect($res->json('data.status'))->toBe($status);
    expect($res->json('data.playback.source'))->toBeNull();
    expect($res->getContent())->not->toContain('SECRET98765');
})->with(['ended', 'offline', 'failed']);

// ─── Optional VOD linkage (independent domain, public-only) ─────────────────────

it('links an optional public VOD on an ended broadcast', function (): void {
    $video = pbPublicVideo(['title' => 'إعادة الحدث']);
    $b = pbMake('ended', 'live', ['vod_video_id' => $video->id]);

    $res = $this->getJson("/api/v1/live/{$b->slug}")->assertOk();

    expect($res->json('data.playback.state'))->toBe('ended');
    expect($res->json('data.playback.vod.id'))->toBe($video->id);
    expect($res->json('data.playback.vod.slug'))->toBe($video->slug);
    expect($res->json('data.playback.vod.canonical_path'))->toContain('/ar/videos/');
});

it('never surfaces a non-public VOD video', function (): void {
    $draftVideo = pbPublicVideo(['title' => 'مسودة فيديو', 'status' => 'draft', 'published_at' => null]);
    $b = pbMake('ended', 'live', ['vod_video_id' => $draftVideo->id]);

    $res = $this->getJson("/api/v1/live/{$b->slug}")->assertOk();

    expect($res->json('data.playback.vod'))->toBeNull();
});

// ─── SEO payload (production-grade structured data, no fakery) ──────────────────

it('builds a VideoObject for video kinds with a live BroadcastEvent publication', function (): void {
    $b = pbMake('live', 'live', ['title' => 'حدث مباشر']);

    $seo = $this->getJson("/api/v1/live/{$b->slug}")->assertOk()->json('data.seo');

    expect($seo['structured_data']['@type'])->toBe('VideoObject');
    expect($seo['structured_data']['publication']['@type'])->toBe('BroadcastEvent');
    expect($seo['structured_data']['publication']['isLiveBroadcast'])->toBeTrue();
    expect($seo['og']['locale'])->toBe('ar_AR');
    expect($seo['canonical_url'])->toContain("/live/{$b->slug}");
});

it('marks the BroadcastEvent as not-live for an upcoming scheduled broadcast', function (): void {
    $b = pbMake('scheduled', 'live');

    $seo = $this->getJson("/api/v1/live/{$b->slug}")->assertOk()->json('data.seo');

    expect($seo['structured_data']['publication']['isLiveBroadcast'])->toBeFalse();
    expect($seo['structured_data']['publication']['startDate'])->not->toBeNull();
});

it('builds an AudioObject for a radio broadcast', function (): void {
    $b = pbMake('live', 'radio', ['source_type' => 'icecast', 'source_url' => 'https://stream.allowed.test/radio']);

    $seo = $this->getJson("/api/v1/radio/{$b->slug}")->assertOk()->json('data.seo');

    expect($seo['structured_data']['@type'])->toBe('AudioObject');
});

// ─── Card vs detail shape + caching contract ────────────────────────────────────

it('omits the heavy SEO/playback block from list cards but keeps it on detail', function (): void {
    $b = pbMake('live', 'live', ['title' => 'شكل', 'slug' => 'broadcast-shape']);

    $card = collect($this->getJson('/api/v1/live')->assertOk()->json('data'))
        ->firstWhere('slug', 'broadcast-shape');
    expect($card)->not->toBeNull();
    expect($card)->not->toHaveKey('seo');
    expect($card)->not->toHaveKey('playback');
    expect($card)->not->toHaveKey('source_url');
    expect($card)->toHaveKeys(['canonical_path', 'share_image', 'kind', 'status']);

    $detail = $this->getJson("/api/v1/live/{$b->slug}")->assertOk();
    expect($detail->json('data'))->toHaveKeys(['seo', 'playback']);
});

it('serves public broadcast surfaces as CDN-cacheable', function (): void {
    pbMake('live');

    $res = $this->getJson('/api/v1/live')->assertOk();

    expect($res->headers->get('Cache-Control'))->toContain('public');
});

it('busts cached live status on a lifecycle transition (no stale live)', function (): void {
    $b = pbMake('live', 'live', ['title' => 'تحوّل']);

    // التفاصيل تُخزَّن بحالة live، والقائمة تُدرجه.
    expect($this->getJson("/api/v1/live/{$b->slug}")->json('data.playback.state'))->toBe('live');
    expect(collect($this->getJson('/api/v1/live')->json('data'))->pluck('slug'))->toContain($b->slug);

    // إنهاء (live → ended) يُبطل وسوم الكاش (تفاصيل + خلاصة النوع).
    (new EndBroadcastAction)->handle($b->fresh());

    // التفاصيل تعكس الحالة الجديدة فوراً (لا live قديمة)، والقائمة لم تعد تُدرجه.
    expect($this->getJson("/api/v1/live/{$b->slug}")->json('data.playback.state'))->toBe('ended');
    expect(collect($this->getJson('/api/v1/live')->json('data'))->pluck('slug'))->not->toContain($b->slug);
});
