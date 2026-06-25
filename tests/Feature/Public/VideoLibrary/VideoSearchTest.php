<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Models\Video;
use App\Models\VideoCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

function vsExternalAsset(): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'external', 'disk' => 'external', 'path' => '',
        'filename' => '', 'original_name' => 'x', 'mime_type' => 'video/external', 'extension' => '',
        'size' => 0, 'checksum' => hash('sha256', Str::random()), 'provider' => 'youtube',
        'provider_id' => Str::random(11), 'embed_url' => 'https://www.youtube.com/embed/'.Str::random(11),
        'source_url' => 'https://youtu.be/'.Str::random(11), 'poster_url' => 'https://img.youtube.com/x.jpg',
        'visibility' => 'public',
    ]);
}

function vsUploadedAsset(): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'video', 'disk' => 'public', 'path' => 'assets/'.Str::random(8).'.mp4',
        'filename' => 'c.mp4', 'original_name' => 'c.mp4', 'mime_type' => 'video/mp4', 'extension' => 'mp4',
        'size' => 4096, 'checksum' => hash('sha256', Str::random()), 'processing_status' => 'ready', 'visibility' => 'public',
        'conversions' => ['hls' => ['master' => 'assets/h/m.m3u8'], 'renditions' => ['master' => 'assets/r/m.mp4', 'variants' => ['720p' => 'assets/r/720.mp4']]],
    ]);
}

function vsVideo(string $locale, string $title, string $description, array $opts = []): Video
{
    $uploaded = (bool) ($opts['uploaded'] ?? false);
    unset($opts['uploaded']);
    $asset = $uploaded ? vsUploadedAsset() : vsExternalAsset();

    return Video::create(array_merge([
        'title' => $title, 'locale' => $locale, 'status' => 'published', 'visibility' => 'public',
        'published_at' => now()->subMinute(), 'description' => $description,
        'media_asset_id' => $asset->id, 'source_type' => $uploaded ? 'uploaded' : 'youtube',
    ], $opts));
}

// ─── Scout-backed full-text search ────────────────────────────────────────────

it('finds videos by DESCRIPTION text via Scout (not just title)', function (): void {
    vsVideo('ar', 'عنوان عام أول', 'وقع زلزال قوي في المنطقة الشرقية اليوم.');
    vsVideo('ar', 'عنوان عام ثاني', 'أخبار رياضية متنوّعة.');

    $res = $this->getJson('/api/v1/ar/videos?filter[q]=زلزال')->assertOk();

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.title'))->toBe('عنوان عام أول');
});

it('still matches on the title', function (): void {
    vsVideo('ar', 'الاقتصاد الوطني ينمو', 'تفاصيل.');
    vsVideo('ar', 'رياضة', 'تفاصيل أخرى.');

    $res = $this->getJson('/api/v1/ar/videos?filter[q]=الاقتصاد')->assertOk();

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.title'))->toBe('الاقتصاد الوطني ينمو');
});

it('isolates search results by locale', function (): void {
    vsVideo('ar', 'مقطع عربي', 'كلمة مميزة فريدة هنا.');
    vsVideo('en', 'English clip', 'uniquekeyword appears here.');

    // البحث بالكلمة الإنجليزية تحت ar يجب ألّا يُرجع فيديو en.
    $res = $this->getJson('/api/v1/ar/videos?filter[q]=uniquekeyword')->assertOk();
    expect($res->json('data'))->toHaveCount(0);

    // وتحت en يُرجعه.
    $en = $this->getJson('/api/v1/en/videos?filter[q]=uniquekeyword')->assertOk();
    expect($en->json('data'))->toHaveCount(1);
});

it('returns an empty set for a non-matching query (no 500)', function (): void {
    vsVideo('ar', 'عنوان', 'محتوى عادي.');

    $res = $this->getJson('/api/v1/ar/videos?filter[q]=لا_يوجد_مطابق_xyz')->assertOk();
    expect($res->json('data'))->toHaveCount(0);
});

it('never surfaces a non-public video from search (public invariant)', function (): void {
    // المسودّة هي الوحيدة التي تحوي الكلمة ⇒ يجب ألّا تظهر في البحث العام.
    vsVideo('ar', 'مسودة سرّية', 'تحتوي كلمة secretword الفريدة', ['status' => 'draft', 'published_at' => null]);

    $res = $this->getJson('/api/v1/ar/videos?filter[q]=secretword')->assertOk();
    expect($res->json('data'))->toHaveCount(0);
});

it('combines search with a category filter', function (): void {
    $cat = VideoCategory::create(['locale' => 'ar', 'name' => 'تقنية', 'is_active' => true]);
    vsVideo('ar', 'داخل التصنيف', 'كلمة بحثية مشتركة هنا.', ['video_category_id' => $cat->id]);
    vsVideo('ar', 'خارج التصنيف', 'كلمة بحثية مشتركة أيضاً.');

    $res = $this->getJson("/api/v1/ar/videos?filter[q]=بحثية&filter[category]={$cat->slug}")->assertOk();

    expect(collect($res->json('data'))->pluck('title')->all())->toBe(['داخل التصنيف']);
});

it('combines search with a source_type filter', function (): void {
    vsVideo('ar', 'خارجي', 'وسم بحثي مشترك.');                       // youtube
    vsVideo('ar', 'مرفوع', 'وسم بحثي مشترك.', ['uploaded' => true]); // uploaded

    $res = $this->getJson('/api/v1/ar/videos?filter[q]=بحثي&filter[source_type]=uploaded')->assertOk();

    expect(collect($res->json('data'))->pluck('title')->all())->toBe(['مرفوع']);
});

it('ignores a too-short search query (no Scout call, returns all)', function (): void {
    vsVideo('ar', 'أول', 'محتوى.');
    vsVideo('ar', 'ثاني', 'محتوى.');

    // حرف واحد < الحدّ الأدنى ⇒ يُتجاهَل البحث، تُعاد كل الفيديوهات.
    $res = $this->getJson('/api/v1/ar/videos?filter[q]=ا')->assertOk();
    expect($res->json('data'))->toHaveCount(2);
});
