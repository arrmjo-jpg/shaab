<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Settings\MediaStorageSettings;
use App\Support\Media\MediaDeliveryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function mdrAsset(array $attrs = []): MediaAsset
{
    return MediaAsset::create(array_merge([
        'uuid' => 'mdr-'.uniqid(),
        'disk' => 'uploads',
        'path' => 'assets/'.uniqid().'/source.mp4',
        'filename' => 'source.mp4',
        'original_name' => 'source.mp4',
        'extension' => 'mp4',
        'size' => 1024,
        'mime_type' => 'video/mp4',
        'visibility' => 'public',
        'stored_local' => true,
        'stored_remote' => false,
        'preferred_delivery' => 'auto',
    ], $attrs));
}

function mdrEnableRemote(bool $healthy): void
{
    app(MediaStorageSettings::class)->remote_enabled = true;
    Cache::put('media:remote:healthy', $healthy, 60);
}

// ─── remote disabled (current operational state) ────────────────────────────

it('serves local when remote is disabled', function (): void {
    $a = mdrAsset(['disk' => 'uploads', 'stored_remote' => true]); // even if a remote copy exists
    expect(MediaDeliveryResolver::diskNameFor($a))->toBe('uploads');
});

// ─── legacy remote-only must keep working regardless of toggle/health ───────

it('serves remote for legacy remote-only assets regardless of toggle', function (): void {
    $a = mdrAsset(['disk' => 's3', 'stored_local' => false, 'stored_remote' => true, 'preferred_delivery' => 'remote']);
    expect(MediaDeliveryResolver::diskNameFor($a))->toBe('s3'); // remote disabled, still remote (no local copy)
});

// ─── hybrid auto: enabled + healthy + synced → remote ───────────────────────

it('serves remote when enabled, healthy and synced (auto)', function (): void {
    mdrEnableRemote(healthy: true);
    $a = mdrAsset(['disk' => 'uploads', 'stored_local' => true, 'stored_remote' => true]);
    expect(MediaDeliveryResolver::diskNameFor($a))->toBe(config('media-library.remote_disk'));
});

// ─── automatic fallback to local when remote unhealthy ──────────────────────

it('falls back to local when remote is unhealthy', function (): void {
    mdrEnableRemote(healthy: false);
    $a = mdrAsset(['disk' => 'uploads', 'stored_local' => true, 'stored_remote' => true]);
    expect(MediaDeliveryResolver::diskNameFor($a))->toBe('uploads');
});

// ─── per-asset pin ──────────────────────────────────────────────────────────

it('respects preferred_delivery=local even when remote healthy', function (): void {
    mdrEnableRemote(healthy: true);
    $a = mdrAsset(['disk' => 'uploads', 'stored_local' => true, 'stored_remote' => true, 'preferred_delivery' => 'local']);
    expect(MediaDeliveryResolver::diskNameFor($a))->toBe('uploads');
});

// ─── local disk URL must be LOCAL, never the remote (R2) public URL ─────────

it('configures the local uploads disk with a local url, independent of MEDIA_URL', function (): void {
    $appBase = rtrim(env('APP_URL', 'http://localhost'), '/').'/uploads';

    // القرص المحلّي canonical يولّد رابطاً محلّياً فقط (لا MEDIA_URL/R2).
    expect(config('filesystems.disks.uploads.url'))->toBe($appBase);

    // حارس: لا يساوي دومين MEDIA_URL (R2) إن كانت مضبوطة ومختلفة.
    $mediaUrl = env('MEDIA_URL');
    if ($mediaUrl && rtrim((string) $mediaUrl, '/') !== $appBase) {
        expect(config('filesystems.disks.uploads.url'))->not->toBe(rtrim((string) $mediaUrl, '/'));
    }
});

it('resolves a local asset URL through the local uploads disk', function (): void {
    $a = mdrAsset([
        'disk' => 'uploads',
        'stored_local' => true,
        'stored_remote' => false,
        'path' => 'assets/zz/v.mp4',
    ]);

    expect(MediaDeliveryResolver::url($a, $a->path))->toContain('/uploads/assets/zz/v.mp4');
});

// ─── API shape preserved (accessors still return URLs) ──────────────────────

it('poster/url accessors still return URLs via the resolver', function (): void {
    Storage::fake('uploads');
    $a = mdrAsset([
        'disk' => 'uploads',
        'conversions' => ['poster' => ['path' => 'assets/x/poster.jpg', 'width' => 720, 'height' => 1280]],
    ]);

    expect($a->posterUrl())->toBeString()->toContain('assets/x/poster.jpg');
    expect($a->url())->toBeString()->toContain($a->path);
});
