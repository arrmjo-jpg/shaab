<?php

declare(strict_types=1);

use App\Models\EngagementCounter;
use App\Models\MediaAsset;
use App\Models\PlaylistUrlHistory;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\VideoPlaylist;
use App\Models\VideoUrlHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** أصل خارجي صالح (يوتيوب) — قابل للتشغيل فوراً. */
function vlExternal(array $attrs = []): MediaAsset
{
    return MediaAsset::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'kind' => 'external',
        'disk' => 'external',
        'path' => '',
        'filename' => '',
        'original_name' => 'https://youtu.be/dQw4w9WgXcQ',
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
    ], $attrs));
}

/** أصل مرفوع — يمرّ بخط HLS؛ الحالة الافتراضية ready (قابل للتشغيل). */
function vlUploaded(string $status = 'ready'): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'video',
        'disk' => 'public',
        'path' => 'assets/'.Str::random(8).'.mp4',
        'filename' => 'clip.mp4',
        'original_name' => 'clip.mp4',
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
        'size' => 4096,
        'checksum' => hash('sha256', Str::random()),
        'processing_status' => $status,
        'visibility' => 'public',
        'conversions' => [
            'poster' => ['path' => 'assets/poster.jpg'],
            'hls' => ['master' => 'assets/hls/master.m3u8'],
            'renditions' => ['master' => 'assets/r/master.mp4', 'variants' => ['720p' => 'assets/r/720.mp4']],
        ],
    ]);
}

/** فيديو عام قابل للتشغيل (أصل خارجي افتراضياً). */
function makePublicVideo(array $attrs = [], ?MediaAsset $asset = null): Video
{
    $asset ??= vlExternal();

    return Video::create(array_merge([
        'title' => 'فيديو '.uniqid(),
        'locale' => 'ar',
        'status' => 'published',
        'visibility' => 'public',
        'published_at' => now()->subMinute(),
        'media_asset_id' => $asset->id,
        'source_type' => $asset->isExternal() ? 'youtube' : 'uploaded',
    ], $attrs));
}

function videoCounter(Video $video, array $metrics): void
{
    EngagementCounter::create(array_merge([
        'engageable_type' => (new Video)->getMorphClass(),
        'engageable_id' => $video->id,
        'views' => 0, 'likes' => 0, 'dislikes' => 0, 'favorites' => 0,
    ], $metrics));
}

// ─── Listing + public invariants ──────────────────────────────────────────────

it('lists only published, public, playable videos for the locale', function (): void {
    $ok = makePublicVideo(['title' => 'منشور عام']);
    makePublicVideo(['title' => 'مسودة', 'status' => 'draft', 'published_at' => null]);
    makePublicVideo(['title' => 'مؤرشف', 'status' => 'archived']);
    makePublicVideo(['title' => 'خاص', 'visibility' => 'private']);
    makePublicVideo(['title' => 'غير مدرج', 'visibility' => 'unlisted']);
    makePublicVideo(['title' => 'إنجليزي', 'locale' => 'en']);
    // مرفوع قيد المعالجة — مصدر موجود لكن غير جاهز ⇒ يُستبعَد من العام.
    makePublicVideo(['title' => 'قيد المعالجة'], vlUploaded('processing'));
    // خارجي معطوب (بلا embed_url) ⇒ يُستبعَد.
    makePublicVideo(['title' => 'خارجي معطوب'], vlExternal(['embed_url' => null]));

    $titles = collect($this->getJson('/api/v1/ar/videos')->assertOk()->json('data'))->pluck('title');

    expect($titles)->toContain('منشور عام');
    expect($titles)->not->toContain('مسودة');
    expect($titles)->not->toContain('مؤرشف');
    expect($titles)->not->toContain('خاص');
    expect($titles)->not->toContain('غير مدرج');
    expect($titles)->not->toContain('إنجليزي');
    expect($titles)->not->toContain('قيد المعالجة');
    expect($titles)->not->toContain('خارجي معطوب');

    expect($ok)->not->toBeNull();
});

it('paginates with offset meta by default and cursor meta on demand', function (): void {
    makePublicVideo();
    makePublicVideo();
    makePublicVideo();

    $offset = $this->getJson('/api/v1/ar/videos?per_page=2')->assertOk();
    expect($offset->json('meta.pagination.per_page'))->toBe(2);
    expect($offset->json('meta.pagination.total'))->toBe(3);
    expect($offset->json('data'))->toHaveCount(2);

    $cursor = $this->getJson('/api/v1/ar/videos?per_page=2&paginate=cursor')->assertOk();
    expect($cursor->json('meta.cursor.has_more'))->toBeTrue();
    expect($cursor->json('meta.cursor.next_cursor'))->not->toBeNull();
    expect($cursor->json('data'))->toHaveCount(2);
});

it('filters by q, source_type and category slug', function (): void {
    $cat = VideoCategory::create(['locale' => 'ar', 'name' => 'رياضة', 'is_active' => true]);

    makePublicVideo(['title' => 'Special Marker Video', 'video_category_id' => $cat->id]);
    makePublicVideo(['title' => 'فيديو عادي آخر'], vlUploaded('ready'));

    // أحدث المرفوع له source_type=uploaded.
    $bySource = $this->getJson('/api/v1/ar/videos?filter[source_type]=uploaded')->assertOk();
    expect(collect($bySource->json('data'))->pluck('source_type')->unique()->all())->toBe(['uploaded']);

    $byQ = $this->getJson('/api/v1/ar/videos?filter[q]=Marker')->assertOk();
    expect(collect($byQ->json('data'))->pluck('title'))->toContain('Special Marker Video');
    expect(collect($byQ->json('data'))->pluck('title'))->not->toContain('فيديو عادي آخر');

    $byCat = $this->getJson("/api/v1/ar/videos?filter[category]={$cat->slug}")->assertOk();
    expect(collect($byCat->json('data'))->pluck('title')->all())->toBe(['Special Marker Video']);
});

// ─── Detail by slug + visibility nuance ─────────────────────────────────────────

it('shows a public video by slug with sanitized seo/media/metrics', function (): void {
    $video = makePublicVideo(['title' => 'مقطع مهم']);

    $res = $this->getJson("/api/v1/ar/videos/{$video->slug}")->assertOk();

    expect($res->json('data.slug'))->toBe($video->slug);
    expect($res->json('data.canonical_path'))->toBe("/ar/videos/{$video->id}-{$video->slug}");
    expect($res->json('data'))->toHaveKeys(['seo', 'metrics', 'media', 'share_image']);
    expect($res->json('data.media.kind'))->toBe('external');
    expect($res->json('data.media.embed_url'))->not->toBeNull();
    expect($res->json('data.seo.structured_data.@type'))->toBe('VideoObject');
});

it('serves an unlisted video by direct slug but never a draft/private/processing one', function (): void {
    $unlisted = makePublicVideo(['title' => 'غير مدرج', 'visibility' => 'unlisted']);
    $private = makePublicVideo(['title' => 'خاص', 'visibility' => 'private']);
    $draft = makePublicVideo(['title' => 'مسودة', 'status' => 'draft', 'published_at' => null]);
    $processing = makePublicVideo(['title' => 'معالجة'], vlUploaded('processing'));

    $this->getJson("/api/v1/ar/videos/{$unlisted->slug}")->assertOk();
    $this->getJson("/api/v1/ar/videos/{$private->slug}")->assertStatus(404);
    $this->getJson("/api/v1/ar/videos/{$draft->slug}")->assertStatus(404);
    $this->getJson("/api/v1/ar/videos/{$processing->slug}")->assertStatus(404);
});

it('exposes a VideoObject contentUrl for uploaded media and embedUrl for external', function (): void {
    $uploaded = makePublicVideo(['title' => 'مرفوع'], vlUploaded('ready'));
    $external = makePublicVideo(['title' => 'خارجي']);

    $up = $this->getJson("/api/v1/ar/videos/{$uploaded->slug}")->assertOk();
    expect($up->json('data.media.kind'))->toBe('uploaded');
    expect($up->json('data.media.hls'))->not->toBeNull();
    expect($up->json('data.seo.structured_data.contentUrl'))->not->toBeNull();

    $ex = $this->getJson("/api/v1/ar/videos/{$external->slug}")->assertOk();
    expect($ex->json('data.seo.structured_data.embedUrl'))->not->toBeNull();
    expect($ex->json('data.seo.og.video_type'))->toBe('text/html');
});

// ─── Featured + trending (real signals) ─────────────────────────────────────────

it('featured returns only is_featured public videos', function (): void {
    makePublicVideo(['title' => 'مميز', 'is_featured' => true]);
    makePublicVideo(['title' => 'عادي']);

    $titles = collect($this->getJson('/api/v1/ar/videos/featured')->assertOk()->json('data'))->pluck('title');
    expect($titles)->toContain('مميز');
    expect($titles)->not->toContain('عادي');
});

it('trending orders by weighted engagement, not recency, and excludes non-playable', function (): void {
    $a = makePublicVideo(['title' => 'A']);
    videoCounter($a, ['views' => 10]); // 10
    $b = makePublicVideo(['title' => 'B']);
    videoCounter($b, ['likes' => 10, 'favorites' => 10]); // 40 + 60 = 100
    $c = makePublicVideo(['title' => 'C']);
    videoCounter($c, ['views' => 50]); // 50

    // مرفوع قيد المعالجة بتفاعل عالٍ — يجب أن يُستبعَد رغم العدّاد.
    $proc = makePublicVideo(['title' => 'معالجة'], vlUploaded('processing'));
    videoCounter($proc, ['favorites' => 999]);

    $titles = collect($this->getJson('/api/v1/ar/videos/trending')->assertOk()->json('data'))->pluck('title')->all();

    expect($titles)->toBe(['B', 'C', 'A']);
});

// ─── Category feed + related ────────────────────────────────────────────────────

it('returns a category feed and 404s for an inactive/unknown category', function (): void {
    $cat = VideoCategory::create(['locale' => 'ar', 'name' => 'تقنية', 'is_active' => true]);
    $inactive = VideoCategory::create(['locale' => 'ar', 'name' => 'مخفي', 'is_active' => false]);

    makePublicVideo(['title' => 'داخل التصنيف', 'video_category_id' => $cat->id]);
    makePublicVideo(['title' => 'خارج التصنيف']);

    $res = $this->getJson("/api/v1/ar/video-categories/{$cat->slug}")->assertOk();
    expect(collect($res->json('data'))->pluck('title')->all())->toBe(['داخل التصنيف']);
    expect($res->json('meta.category.slug'))->toBe($cat->slug);

    $this->getJson("/api/v1/ar/video-categories/{$inactive->slug}")->assertStatus(404);
    $this->getJson('/api/v1/ar/video-categories/does-not-exist')->assertStatus(404);
});

it('returns related videos from the same category excluding the source', function (): void {
    $cat = VideoCategory::create(['locale' => 'ar', 'name' => 'سفر', 'is_active' => true]);
    $source = makePublicVideo(['title' => 'المصدر', 'video_category_id' => $cat->id]);
    makePublicVideo(['title' => 'شقيق', 'video_category_id' => $cat->id]);
    makePublicVideo(['title' => 'تصنيف آخر']);

    $titles = collect($this->getJson("/api/v1/ar/videos/{$source->slug}/related")->assertOk()->json('data'))->pluck('title');

    expect($titles)->toContain('شقيق');
    expect($titles)->not->toContain('المصدر');
    expect($titles)->not->toContain('تصنيف آخر');
});

// ─── 301 redirects (SEO migration) ──────────────────────────────────────────────

it('301-redirects an old video slug to the current canonical', function (): void {
    $video = makePublicVideo(['title' => 'متغيّر', 'slug' => 'new-slug']);
    VideoUrlHistory::create([
        'video_id' => $video->id,
        'locale' => 'ar',
        'old_path' => "/ar/videos/{$video->id}-old-slug",
        'reason' => 'slug_change',
    ]);

    // نقطة التفاصيل بالـ slug القديم → تُعيد التوجيه إلى نقطة الـ API بالـ slug الحالي.
    $bySlug = $this->getJson('/api/v1/ar/videos/old-slug');
    $bySlug->assertStatus(301);
    expect($bySlug->headers->get('Location'))->toContain('/api/v1/ar/videos/new-slug');

    // نقطة المُحلِّل بالمسار الكامل → تُعيد التوجيه إلى المسار القانوني (id-slug) للواجهة.
    $byPath = $this->getJson("/api/v1/ar/redirects/videos?path=/ar/videos/{$video->id}-old-slug");
    $byPath->assertStatus(301);
    expect($byPath->headers->get('Location'))->toContain("/ar/videos/{$video->id}-new-slug");

    // لا تطابق ⇒ 404.
    $this->getJson('/api/v1/ar/redirects/videos?path=/ar/videos/999-nope')->assertStatus(404);
});

// ─── Playlists ───────────────────────────────────────────────────────────────

function makePublicPlaylist(array $attrs = []): VideoPlaylist
{
    return VideoPlaylist::create(array_merge([
        'title' => 'قائمة '.uniqid(),
        'locale' => 'ar',
        'status' => 'published',
        'visibility' => 'public',
        'published_at' => now()->subMinute(),
    ], $attrs));
}

it('lists public playlists with a count of only public playable members', function (): void {
    $playlist = makePublicPlaylist(['title' => 'قائمتي']);
    makePublicPlaylist(['title' => 'مسودة قائمة', 'status' => 'draft', 'published_at' => null]);

    $pub = makePublicVideo();
    $draft = makePublicVideo(['status' => 'draft', 'published_at' => null]);
    $playlist->videos()->attach($pub->id, ['position' => 1]);
    $playlist->videos()->attach($draft->id, ['position' => 2]);

    $res = $this->getJson('/api/v1/ar/playlists')->assertOk();
    $row = collect($res->json('data'))->firstWhere('title', 'قائمتي');

    expect(collect($res->json('data'))->pluck('title'))->not->toContain('مسودة قائمة');
    expect($row['videos_count'])->toBe(1); // المسودة لا تُحتسَب
});

it('shows a playlist by slug and embeds only public playable videos in order', function (): void {
    $playlist = makePublicPlaylist(['title' => 'تشغيل', 'slug' => 'mix']);
    $v1 = makePublicVideo(['title' => 'الأول']);
    $v2 = makePublicVideo(['title' => 'الثاني']);
    $hidden = makePublicVideo(['title' => 'مخفي', 'visibility' => 'private']);

    $playlist->videos()->attach($v2->id, ['position' => 1]); // position يحكم الترتيب
    $playlist->videos()->attach($v1->id, ['position' => 2]);
    $playlist->videos()->attach($hidden->id, ['position' => 3]);

    $res = $this->getJson('/api/v1/ar/playlists/mix')->assertOk();

    expect(collect($res->json('data.videos'))->pluck('title')->all())->toBe(['الثاني', 'الأول']);
    expect($res->json('data.seo.structured_data.@type'))->toBe('ItemList');
});

it('never serves a private playlist but serves an unlisted one by direct slug', function (): void {
    $private = makePublicPlaylist(['title' => 'خاص', 'visibility' => 'private']);
    $unlisted = makePublicPlaylist(['title' => 'غير مدرج', 'visibility' => 'unlisted']);

    $this->getJson("/api/v1/ar/playlists/{$private->slug}")->assertStatus(404);
    $this->getJson("/api/v1/ar/playlists/{$unlisted->slug}")->assertOk();
    // غير المُدرَجة لا تظهر في القائمة العامة.
    expect(collect($this->getJson('/api/v1/ar/playlists')->json('data'))->pluck('title'))
        ->not->toContain('غير مدرج');
});

it('301-redirects an old playlist slug to the current canonical', function (): void {
    $playlist = makePublicPlaylist(['title' => 'محوّلة', 'slug' => 'fresh']);
    PlaylistUrlHistory::create([
        'video_playlist_id' => $playlist->id,
        'locale' => 'ar',
        'old_path' => "/ar/playlists/{$playlist->id}-stale",
        'reason' => 'slug_change',
    ]);

    $res = $this->getJson('/api/v1/ar/playlists/stale');
    $res->assertStatus(301);
    expect($res->headers->get('Location'))->toContain('/api/v1/ar/playlists/fresh');

    // ومُحلِّل المسار الكامل يُعيد المسار القانوني (id-slug) للواجهة.
    $byPath = $this->getJson("/api/v1/ar/redirects/playlists?path=/ar/playlists/{$playlist->id}-stale");
    $byPath->assertStatus(301);
    expect($byPath->headers->get('Location'))->toContain("/ar/playlists/{$playlist->id}-fresh");
});

// ─── Sitemaps ────────────────────────────────────────────────────────────────

it('renders a video sitemap with the Google video extension', function (): void {
    $video = makePublicVideo(['title' => 'في الخريطة']);
    makePublicVideo(['title' => 'مسودة', 'status' => 'draft', 'published_at' => null]);

    $res = $this->get('/sitemap-videos-ar.xml')->assertOk();
    $res->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $body = $res->getContent();
    expect($body)->toContain("/ar/videos/{$video->id}-");
    expect($body)->toContain('video:'); // امتداد فيديو Google (poster متوفّر)
});

it('renders playlist and video-category sitemaps and lists them in the index', function (): void {
    $playlist = makePublicPlaylist(['title' => 'قائمة الخريطة', 'slug' => 'sm']);
    $cat = VideoCategory::create(['locale' => 'ar', 'name' => 'وثائقي', 'is_active' => true]);

    $pl = $this->get('/sitemap-playlists-ar.xml')->assertOk();
    expect($pl->getContent())->toContain("/ar/playlists/{$playlist->id}-sm");

    $vc = $this->get('/sitemap-video-categories-ar.xml')->assertOk();
    expect($vc->getContent())->toContain("/ar/video-categories/{$cat->slug}");

    $index = $this->get('/sitemap.xml')->assertOk();
    expect($index->getContent())->toContain('sitemap-videos-ar.xml');
    expect($index->getContent())->toContain('sitemap-playlists-ar.xml');
    expect($index->getContent())->toContain('sitemap-video-categories-ar.xml');
});

// ─── P5.1 hardening ────────────────────────────────────────────────────────────

it('throttles public reads beyond the configured per-client limit', function (): void {
    config(['performance.public_read_rate_limit' => 3]);
    makePublicVideo();
    $headers = ['X-Client-Id' => 'throttle-probe-'.uniqid()];

    for ($i = 0; $i < 3; $i++) {
        $this->withHeaders($headers)->getJson('/api/v1/ar/videos')->assertOk();
    }
    // الطلب الرابع يتجاوز السقف ⇒ 429.
    $this->withHeaders($headers)->getJson('/api/v1/ar/videos')->assertStatus(429);
});

it('ignores a too-short q filter but applies a valid one', function (): void {
    makePublicVideo(['title' => 'Football Highlights']);
    makePublicVideo(['title' => 'Cooking Show']);

    // حرف واحد → يُتجاهَل (لا LIKE) فتُعاد كل الفيديوهات.
    $short = $this->getJson('/api/v1/ar/videos?filter[q]=F')->assertOk();
    expect($short->json('data'))->toHaveCount(2);

    // ≥ حرفين → يُطبَّق.
    $valid = $this->getJson('/api/v1/ar/videos?filter[q]=Football')->assertOk();
    expect(collect($valid->json('data'))->pluck('title')->all())->toBe(['Football Highlights']);
});

it('omits the heavy SEO block from list cards but keeps it on detail', function (): void {
    makePublicVideo(['title' => 'SEO Shape', 'slug' => 'seo-shape']);

    $card = collect($this->getJson('/api/v1/ar/videos')->assertOk()->json('data'))
        ->firstWhere('slug', 'seo-shape');
    expect($card)->not->toBeNull();
    expect($card)->not->toHaveKey('seo');                    // البطاقة خفيفة (لا N+1)
    expect($card)->toHaveKeys(['media', 'metrics', 'canonical_path']);

    $detail = $this->getJson('/api/v1/ar/videos/seo-shape')->assertOk();
    expect($detail->json('data'))->toHaveKey('seo');         // التفاصيل كاملة
    expect($detail->json('data.seo.structured_data.@type'))->toBe('VideoObject');
});

it('omits external contentUrl for embed providers but keeps it for direct mp4', function (): void {
    $yt = makePublicVideo(['title' => 'YT', 'slug' => 'yt-detail']);
    $mp4Asset = vlExternal(['provider' => 'mp4', 'embed_url' => 'https://cdn.example.com/v.mp4', 'source_url' => 'https://cdn.example.com/v.mp4']);
    $mp4 = makePublicVideo(['title' => 'MP4', 'slug' => 'mp4-detail', 'source_type' => 'direct_mp4'], $mp4Asset);

    $ytSd = $this->getJson('/api/v1/ar/videos/yt-detail')->assertOk()->json('data.seo.structured_data');
    expect($ytSd)->not->toHaveKey('contentUrl');             // يوتيوب: embedUrl فقط
    expect($ytSd['embedUrl'])->not->toBeNull();

    $mp4Sd = $this->getJson('/api/v1/ar/videos/mp4-detail')->assertOk()->json('data.seo.structured_data');
    expect($mp4Sd['contentUrl'])->toBe('https://cdn.example.com/v.mp4'); // MP4 مباشر: ملف فعلي
});

it('includes videos_count on playlist detail counting only public playable members', function (): void {
    $pl = makePublicPlaylist(['title' => 'عدّاد', 'slug' => 'count-pl']);
    $pl->videos()->attach(makePublicVideo()->id, ['position' => 1]);
    $pl->videos()->attach(
        makePublicVideo(['status' => 'draft', 'published_at' => null])->id,
        ['position' => 2]
    );

    $res = $this->getJson('/api/v1/ar/playlists/count-pl')->assertOk();
    expect($res->json('data.videos_count'))->toBe(1);
    expect($res->json('data.videos'))->toHaveCount(1);
});

it('does not fragment public cache by Accept-Language', function (): void {
    makePublicVideo();
    $res = $this->getJson('/api/v1/ar/videos')->assertOk();

    expect($res->headers->get('Cache-Control'))->toContain('public');
    expect((string) $res->headers->get('Vary'))->not->toContain('Accept-Language');
});
