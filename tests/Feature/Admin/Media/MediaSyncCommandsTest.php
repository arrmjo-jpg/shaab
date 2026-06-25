<?php

declare(strict_types=1);

use App\Jobs\MirrorMediaToRemoteJob;
use App\Jobs\PullMediaToLocalJob;
use App\Models\MediaAsset;
use App\Settings\MediaStorageSettings;
use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function syncAsset(array $attrs = []): MediaAsset
{
    $uuid = 'sc-'.uniqid();

    return MediaAsset::create(array_merge([
        'uuid' => $uuid,
        'disk' => 'uploads',
        'path' => "assets/{$uuid}/{$uuid}.mp4",
        'filename' => "{$uuid}.mp4",
        'original_name' => 'reel.mp4',
        'extension' => 'mp4',
        'size' => 10,
        'mime_type' => 'video/mp4',
        'visibility' => 'public',
        'stored_local' => true,
        'stored_remote' => false,
        'remote_sync_status' => 'pending',
        'processing_status' => 'ready',
    ], $attrs));
}

// ─── media:sync:remote (operational backlog) ────────────────────────────────

it('sync dispatches mirror jobs for backlog when remote enabled', function (): void {
    app(MediaStorageSettings::class)->remote_enabled = true;
    Queue::fake();
    syncAsset();

    test()->artisan('media:sync:remote')->assertSuccessful();

    Queue::assertPushed(MirrorMediaToRemoteJob::class);
});

it('sync is a no-op when remote disabled', function (): void {
    Queue::fake();
    syncAsset();

    test()->artisan('media:sync:remote')->assertSuccessful();

    Queue::assertNotPushed(MirrorMediaToRemoteJob::class);
});

it('sync skips already-synced assets', function (): void {
    app(MediaStorageSettings::class)->remote_enabled = true;
    Queue::fake();
    syncAsset(['stored_remote' => true, 'remote_sync_status' => 'synced']);

    test()->artisan('media:sync:remote')->assertSuccessful();

    Queue::assertNotPushed(MirrorMediaToRemoteJob::class);
});

// ─── media:repair:remote --pull ─────────────────────────────────────────────

it('repair --pull dispatches pull jobs for remote-only assets', function (): void {
    Queue::fake();
    syncAsset(['disk' => 's3', 'stored_local' => false, 'stored_remote' => true, 'remote_sync_status' => 'synced', 'preferred_delivery' => 'remote']);

    test()->artisan('media:repair:remote --pull')->assertSuccessful();

    Queue::assertPushed(PullMediaToLocalJob::class);
});

it('repair without --pull fails (only pull supported)', function (): void {
    test()->artisan('media:repair:remote')->assertFailed();
});

// ─── PullMediaToLocalJob (localization, streaming) ──────────────────────────

it('pull job localizes a remote-only asset and flips to local canonical', function (): void {
    Storage::fake('uploads');
    Storage::fake('s3');

    $uuid = 'pl-'.uniqid();
    $prefix = "assets/{$uuid}";
    Storage::disk('s3')->put("{$prefix}/{$uuid}.mp4", 'source');
    Storage::disk('s3')->put("{$prefix}/poster.jpg", 'poster');

    $asset = syncAsset([
        'uuid' => $uuid,
        'disk' => 's3',
        'path' => "{$prefix}/{$uuid}.mp4",
        'filename' => "{$uuid}.mp4",
        'stored_local' => false,
        'stored_remote' => true,
        'remote_sync_status' => 'synced',
        'preferred_delivery' => 'remote',
    ]);

    (new PullMediaToLocalJob($asset->id))->handle();

    Storage::disk('uploads')->assertExists("{$prefix}/{$uuid}.mp4");
    Storage::disk('uploads')->assertExists("{$prefix}/poster.jpg");

    $fresh = $asset->fresh();
    expect($fresh->stored_local)->toBeTrue();
    expect($fresh->disk)->toBe('uploads');
    expect($fresh->preferred_delivery)->toBe('auto');
    expect($fresh->remote_sync_status)->toBe('synced');
});

// ─── media:verify:remote (drift detection) ──────────────────────────────────

it('verify flags drift when the remote object is missing', function (): void {
    Storage::fake('s3'); // remote object intentionally NOT created

    $uuid = 'vf-'.uniqid();
    $asset = syncAsset([
        'uuid' => $uuid,
        'disk' => 's3',
        'path' => "assets/{$uuid}/{$uuid}.mp4",
        'stored_local' => false,
        'stored_remote' => true,
        'remote_sync_status' => 'synced',
    ]);

    test()->artisan('media:verify:remote')->assertSuccessful();

    expect($asset->fresh()->remote_sync_status)->toBe('failed');
});

// ─── scheduler ──────────────────────────────────────────────────────────────

it('registers the 10-minute remote sync schedule', function (): void {
    $def = SchedulerRegistry::find('media_sync_remote');

    expect($def)->not->toBeNull();
    expect($def['command'])->toBe('media:sync:remote');
    expect($def['cron'])->toBe('*/10 * * * *');
});
