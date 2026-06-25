<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Settings\MediaStorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function hsAsset(array $attrs = []): MediaAsset
{
    return MediaAsset::create(array_merge([
        'uuid' => 'hs-'.uniqid(),
        'disk' => 'uploads',
        'path' => 'assets/'.uniqid().'/source.mp4',
        'filename' => 'source.mp4',
        'original_name' => 'source.mp4',
        'extension' => 'mp4',
        'size' => 1024,
        'mime_type' => 'video/mp4',
        'visibility' => 'public',
    ], $attrs))->refresh();
}

it('a new media asset defaults to local-canonical, unsynced', function (): void {
    $a = hsAsset();

    expect($a->stored_local)->toBeTrue();
    expect($a->stored_remote)->toBeFalse();
    expect($a->preferred_delivery)->toBe('auto');
    expect($a->remote_path)->toBeNull();
    expect($a->last_remote_sync_at)->toBeNull();
});

it('hybrid sync columns cast correctly', function (): void {
    $a = hsAsset([
        'stored_remote' => 1,
        'remote_path' => 'assets/x/source.mp4',
        'remote_sync_status' => 'synced',
        'last_remote_sync_at' => now(),
    ]);

    expect($a->stored_remote)->toBeTrue();
    expect($a->last_remote_sync_at)->toBeInstanceOf(Carbon::class);
    expect($a->remote_sync_status)->toBe('synced');
});

it('media storage settings resolve (remote disabled in test env)', function (): void {
    $s = app(MediaStorageSettings::class);

    expect($s->remote_enabled)->toBeFalse();   // MEDIA_REMOTE_ENABLED غير مضبوط في الاختبارات
    expect($s->remote_driver)->toBe('s3');
    expect($s->remote_use_path_style)->toBeTrue();
});
