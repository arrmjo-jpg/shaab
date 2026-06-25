<?php

declare(strict_types=1);

use App\Jobs\MirrorMediaToRemoteJob;
use App\Models\MediaAsset;
use App\Settings\MediaStorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function mmrEnableRemote(): void
{
    app(MediaStorageSettings::class)->remote_enabled = true;
}

/** أصل محلّي مع شجرة مشتقّات كاملة على قرص uploads. */
function mmrAssetWithFiles(): MediaAsset
{
    $uuid = 'mm-'.uniqid();
    $prefix = "assets/{$uuid}";
    $disk = Storage::disk('uploads');
    $disk->put("{$prefix}/{$uuid}.mp4", 'source-bytes');
    $disk->put("{$prefix}/poster.jpg", 'poster');
    $disk->put("{$prefix}/thumbnail.webp", 'webp');
    $disk->put("{$prefix}/720p.mp4", 'rendition');
    $disk->put("{$prefix}/master.mp4", 'master');
    $disk->put("{$prefix}/hls/master.m3u8", 'manifest');
    $disk->put("{$prefix}/hls/stream_0/segment_00000.ts", 'segment');

    return MediaAsset::create([
        'uuid' => $uuid,
        'disk' => 'uploads',
        'path' => "{$prefix}/{$uuid}.mp4",
        'filename' => "{$uuid}.mp4",
        'original_name' => 'reel.mp4',
        'extension' => 'mp4',
        'size' => 12,
        'mime_type' => 'video/mp4',
        'visibility' => 'public',
        'stored_local' => true,
        'stored_remote' => false,
        'remote_sync_status' => 'pending',
    ]);
}

it('mirrors all artifacts (source/poster/webp/renditions/HLS) and marks synced', function (): void {
    Storage::fake('uploads');
    Storage::fake('media_remote');
    mmrEnableRemote();

    $asset = mmrAssetWithFiles();
    $prefix = dirname($asset->path);

    (new MirrorMediaToRemoteJob($asset->id))->handle();

    $remote = Storage::disk('media_remote');
    $remote->assertExists($asset->path);
    $remote->assertExists("{$prefix}/poster.jpg");
    $remote->assertExists("{$prefix}/thumbnail.webp");
    $remote->assertExists("{$prefix}/720p.mp4");
    $remote->assertExists("{$prefix}/master.mp4");
    $remote->assertExists("{$prefix}/hls/master.m3u8");
    $remote->assertExists("{$prefix}/hls/stream_0/segment_00000.ts");

    $fresh = $asset->fresh();
    expect($fresh->stored_remote)->toBeTrue();
    expect($fresh->remote_sync_status)->toBe('synced');
    expect($fresh->remote_path)->toBe($asset->path);
    expect($fresh->last_remote_sync_at)->not->toBeNull();
});

it('is idempotent — safe re-run does not duplicate or error', function (): void {
    Storage::fake('uploads');
    Storage::fake('media_remote');
    mmrEnableRemote();

    $asset = mmrAssetWithFiles();

    (new MirrorMediaToRemoteJob($asset->id))->handle();
    (new MirrorMediaToRemoteJob($asset->id))->handle(); // re-run

    expect($asset->fresh()->remote_sync_status)->toBe('synced');
    expect(Storage::disk('media_remote')->get($asset->path))->toBe('source-bytes');
});

it('is a no-op when remote is disabled', function (): void {
    Storage::fake('uploads');
    Storage::fake('media_remote');
    // remote NOT enabled

    $asset = mmrAssetWithFiles();

    (new MirrorMediaToRemoteJob($asset->id))->handle();

    expect(Storage::disk('media_remote')->exists($asset->path))->toBeFalse();
    expect($asset->fresh()->stored_remote)->toBeFalse();
});

it('fails safely (status failed, no exception) when remote disk is unconfigured', function (): void {
    Storage::fake('uploads');
    // media_remote intentionally NOT faked/configured → resolving it throws
    mmrEnableRemote();

    $asset = mmrAssetWithFiles();

    (new MirrorMediaToRemoteJob($asset->id))->handle(); // must not throw

    $fresh = $asset->fresh();
    expect($fresh->stored_remote)->toBeFalse();
    expect($fresh->remote_sync_status)->toBe('failed');
});
