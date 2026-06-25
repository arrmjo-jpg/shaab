<?php

declare(strict_types=1);

use App\Actions\Admin\VideoLibrary\PublishDueVideosAction;
use App\Enums\VideoStatus;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Video;
use App\Support\Video\Mp4HostAllowList;
use App\Support\Video\VideoMedia;
use App\Support\Video\VideoSourceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function uploadedVideoAsset(): MediaAsset
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
        'size' => 1024,
        'checksum' => hash('sha256', Str::random()),
        'processing_status' => 'ready',
        'visibility' => 'public',
    ]);
}

// ─── CONFIRMATION #2 — strict direct_mp4 allow-list ─────────────────────────

it('permits a direct mp4 only from an allow-listed host over https', function (): void {
    config(['video.mp4_allowed_hosts' => ['example.com']]);

    expect(Mp4HostAllowList::permits('https://cdn.example.com/clip.mp4'))->toBeTrue(); // subdomain match
    expect(Mp4HostAllowList::permits('https://example.com/clip.mp4'))->toBeTrue();
    expect(Mp4HostAllowList::permits('https://evil.test/clip.mp4'))->toBeFalse();      // not allow-listed
    expect(Mp4HostAllowList::permits('http://example.com/clip.mp4'))->toBeFalse();     // not https
    expect(Mp4HostAllowList::permits('https://127.0.0.1/clip.mp4'))->toBeFalse();      // SSRF guard
});

it('rejects all direct mp4 when the allow-list is empty (safe default)', function (): void {
    config(['video.mp4_allowed_hosts' => []]);

    expect(Mp4HostAllowList::permits('https://cdn.example.com/clip.mp4'))->toBeFalse();
    expect(VideoSourceResolver::classify('https://cdn.example.com/clip.mp4'))->toBeNull();
});

it('classifies allowed video-library source types and rejects out-of-scope providers', function (): void {
    config(['video.mp4_allowed_hosts' => ['example.com']]);

    expect(VideoSourceResolver::classify('https://www.youtube.com/watch?v=dQw4w9WgXcQ')['source_type'] ?? null)->toBe('youtube');
    expect(VideoSourceResolver::classify('https://vimeo.com/123456789')['source_type'] ?? null)->toBe('vimeo');
    expect(VideoSourceResolver::classify('https://cdn.example.com/clip.mp4')['source_type'] ?? null)->toBe('direct_mp4');

    // مزوّدون خارج نطاق مكتبة الفيديو يُرفَضون
    expect(VideoSourceResolver::classify('https://www.tiktok.com/@user/video/123456789012345'))->toBeNull();
    expect(VideoSourceResolver::classify('https://instagram.com/reel/abc123'))->toBeNull();
    expect(VideoSourceResolver::classify('not a url'))->toBeNull();
});

// ─── CONFIRMATION #1 — primary media ownership ──────────────────────────────

it('attaches an external source as a shared library asset and sets source_type', function (): void {
    $actor = User::factory()->create();
    $video = Video::factory()->create(['media_asset_id' => null]);

    $ok = VideoMedia::attachExternalSource($video, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $actor);

    expect($ok)->toBeTrue();
    expect($video->fresh()->media_asset_id)->not->toBeNull();
    expect($video->fresh()->source_type)->toBe('youtube');
    expect($video->fresh()->mediaAsset->isExternal())->toBeTrue();
});

it('dedupes a shared external asset across two videos (no duplicate)', function (): void {
    $actor = User::factory()->create();
    $a = Video::factory()->create();
    $b = Video::factory()->create();
    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    VideoMedia::attachExternalSource($a, $url, $actor);
    VideoMedia::attachExternalSource($b, $url, $actor);

    expect($a->fresh()->media_asset_id)->toBe($b->fresh()->media_asset_id); // نفس الأصل المُشترَك
    expect(MediaAsset::where('kind', 'external')->count())->toBe(1);
});

it('attaches an uploaded asset as 1:1 owned media', function (): void {
    $video = Video::factory()->create();
    $asset = uploadedVideoAsset();

    expect(VideoMedia::attachUploadedAsset($video, $asset))->toBeTrue();
    expect($video->fresh()->source_type)->toBe('uploaded');
    expect($video->fresh()->media_asset_id)->toBe($asset->id);
});

it('rejects attaching an external/non-video asset as uploaded', function (): void {
    $video = Video::factory()->create();
    $external = MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'external', 'disk' => 'external', 'path' => '',
        'filename' => '', 'original_name' => 'x', 'mime_type' => 'video/external', 'extension' => '',
        'size' => 0, 'checksum' => hash('sha256', 'x'), 'visibility' => 'public',
    ]);

    expect(VideoMedia::attachUploadedAsset($video, $external))->toBeFalse();
});

it('releases an owned uploaded asset on force-delete but keeps shared/external assets', function (): void {
    // مرفوع مملوك غير مُشترَك → يُحذَف
    $owned = Video::factory()->create();
    $asset = uploadedVideoAsset();
    VideoMedia::attachUploadedAsset($owned, $asset);
    VideoMedia::releaseOwnedAsset($owned->fresh());
    expect(MediaAsset::find($asset->id))->toBeNull();

    // خارجي مُشترَك → يبقى
    $actor = User::factory()->create();
    $ext = Video::factory()->create();
    VideoMedia::attachExternalSource($ext, 'https://vimeo.com/55555555', $actor);
    $extAssetId = $ext->fresh()->media_asset_id;
    VideoMedia::releaseOwnedAsset($ext->fresh());
    expect(MediaAsset::find($extAssetId))->not->toBeNull();
});

it('keeps an uploaded asset shared by another video on force-delete', function (): void {
    $asset = uploadedVideoAsset();
    $a = Video::factory()->create();
    $b = Video::factory()->create();
    VideoMedia::attachUploadedAsset($a, $asset);
    VideoMedia::attachUploadedAsset($b, $asset); // نفس الأصل مُشار إليه من فيديوين

    VideoMedia::releaseOwnedAsset($a->fresh());

    expect(MediaAsset::find($asset->id))->not->toBeNull(); // لا يُحذَف لأنه مُشترَك
});

// ─── Scheduled publishing ────────────────────────────────────────────────────

it('publishes due scheduled videos with ready media and skips those without', function (): void {
    $actor = User::factory()->create();

    $ready = Video::factory()->create([
        'status' => VideoStatus::Scheduled->value,
        'published_at' => now()->subMinute(),
    ]);
    VideoMedia::attachExternalSource($ready, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $actor);
    $ready->update(['status' => VideoStatus::Scheduled->value]); // attach saved; ensure status

    $noMedia = Video::factory()->create([
        'status' => VideoStatus::Scheduled->value,
        'published_at' => now()->subMinute(),
        'media_asset_id' => null,
    ]);

    $future = Video::factory()->create([
        'status' => VideoStatus::Scheduled->value,
        'published_at' => now()->addDay(),
    ]);
    VideoMedia::attachExternalSource($future, 'https://vimeo.com/123456789', $actor);
    $future->update(['status' => VideoStatus::Scheduled->value, 'published_at' => now()->addDay()]);

    $count = (new PublishDueVideosAction)->handle();

    expect($count)->toBe(1);
    expect($ready->fresh()->status)->toBe(VideoStatus::Published);
    expect($noMedia->fresh()->status)->toBe(VideoStatus::Scheduled); // بقي مجدوَلاً (لا وسائط)
    expect($future->fresh()->status)->toBe(VideoStatus::Scheduled);  // لم يحِن وقته
});

it('is idempotent — does not republish already-published videos', function (): void {
    $actor = User::factory()->create();
    $v = Video::factory()->create(['status' => VideoStatus::Scheduled->value, 'published_at' => now()->subMinute()]);
    VideoMedia::attachExternalSource($v, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $actor);
    $v->update(['status' => VideoStatus::Scheduled->value]);

    expect((new PublishDueVideosAction)->handle())->toBe(1);
    expect((new PublishDueVideosAction)->handle())->toBe(0);
});
