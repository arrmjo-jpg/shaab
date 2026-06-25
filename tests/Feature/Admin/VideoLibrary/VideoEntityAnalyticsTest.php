<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\ContentDailyStat;
use App\Models\EngagementCounter;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function vaeSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function vaeVideo(): Video
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
        'title' => 'تحليلات '.uniqid(), 'locale' => 'ar', 'status' => 'published', 'visibility' => 'public',
        'published_at' => now()->subMinute(), 'media_asset_id' => $asset->id, 'source_type' => 'youtube',
    ]);
}

it('returns real per-video analytics with honest cumulative-vs-forward-only split', function (): void {
    $token = vaeSuperToken();
    $video = vaeVideo();
    $morph = $video->getMorphClass();

    // عدّاد تراكمي (كلّ الأزمنة) — مستقلّ عن التجميع اليوميّ (إلى-الأمام).
    EngagementCounter::create([
        'engageable_type' => $morph, 'engageable_id' => $video->id,
        'views' => 100, 'likes' => 10, 'dislikes' => 2, 'favorites' => 5,
    ]);

    // تجميع يوميّ (اليوم) — يغذّي السلاسل الزمنية ومصادر الزيارات.
    ContentDailyStat::create([
        'engageable_type' => $morph, 'engageable_id' => $video->id, 'day' => now()->toDateString(),
        'views' => 30, 'likes' => 4, 'dislikes' => 0, 'favorites' => 1,
        'views_search' => 20, 'views_direct' => 10,
    ]);

    $res = $this->withToken($token)
        ->getJson("/api/v1/admin/videos/{$video->id}/analytics?range=7d")
        ->assertOk();

    // التفاعل: مجاميع تراكمية حقيقية + المعدّل + الفريدون.
    expect($res->json('data.engagement.views'))->toBe(100);
    expect($res->json('data.engagement.likes'))->toBe(10);
    expect($res->json('data.engagement.engagement_rate'))->toEqual(17.0); // (10+2+5)/100*100

    // السلسلة الزمنية: نافذة 7 أيام، مجاميع النطاق من التجميع اليوميّ فقط.
    expect($res->json('data.trend.window.range'))->toBe('7d');
    expect($res->json('data.trend.points'))->toHaveCount(7);
    expect($res->json('data.trend.totals.views'))->toBe(30);
    expect($res->json('data.trend.forward_only'))->toBeTrue();

    // مصادر الزيارات: تفصيل القناة الحقيقي.
    expect($res->json('data.traffic.total'))->toBe(30);
    expect($res->json('data.traffic.channels.search'))->toBe(20);
    expect($res->json('data.traffic.channels.direct'))->toBe(10);

    // مقاييس المشاهدة: مؤجّلة بصدق (لا تيليمتري مشغّل).
    expect($res->json('data.watch.available'))->toBeFalse();

    // أقسام حقيقية أخرى موجودة.
    expect($res->json('data.distribution'))->toBeArray();
    expect($res->json('data.seo.slug'))->toBe($video->slug);
    expect($res->json('data.publishing.status'))->toBe('published');
});

it('surfaces linked VOD broadcasts in the video distribution', function (): void {
    $token = vaeSuperToken();
    $video = vaeVideo();
    Broadcast::factory()->create(['vod_video_id' => $video->id, 'title' => 'إعادة الحدث']);

    $res = $this->withToken($token)
        ->getJson("/api/v1/admin/videos/{$video->id}/analytics")
        ->assertOk();

    expect($res->json('data.distribution.linked_vods'))->toHaveCount(1);
    expect($res->json('data.distribution.linked_vods.0.title'))->toBe('إعادة الحدث');
});

it('requires videos.view to read per-video analytics', function (): void {
    $video = vaeVideo();
    $token = User::factory()->create()->createToken('admin', ['admin'])->plainTextToken; // بلا أدوار/صلاحيات

    $this->withToken($token)->getJson("/api/v1/admin/videos/{$video->id}/analytics")->assertStatus(403);
});
