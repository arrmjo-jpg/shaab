<?php

declare(strict_types=1);

use App\Jobs\MirrorMediaToRemoteJob;
use App\Models\MediaAsset;
use App\Models\User;
use App\Settings\MediaStorageSettings;
use App\Support\Media\RemoteStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function mediaStorageToken(): string
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return $admin->createToken('admin-token', ['admin'])->plainTextToken;
}

function mediaStorageAsset(array $attrs = []): MediaAsset
{
    $uuid = 'ms-'.uniqid();

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

// ─── Read: status + backlog ──────────────────────────────────────────────

it('returns hybrid storage status with masked secrets and backlog counts', function (): void {
    $token = mediaStorageToken();

    $settings = app(MediaStorageSettings::class);
    $settings->remote_key = 'AKIAEXAMPLE';
    $settings->remote_secret = 'super-secret-value';
    $settings->save();

    mediaStorageAsset(['remote_sync_status' => 'pending']);
    mediaStorageAsset(['remote_sync_status' => 'failed']);
    mediaStorageAsset(['stored_remote' => true, 'remote_sync_status' => 'synced']);

    $this->withToken($token)->getJson('/api/v1/admin/settings/media-storage')
        ->assertOk()
        ->assertJsonPath('data.settings.remote_key', '********')
        ->assertJsonPath('data.settings.remote_key_configured', true)
        ->assertJsonPath('data.settings.remote_secret', '********')
        ->assertJsonPath('data.settings.remote_secret_configured', true)
        ->assertJsonPath('data.backlog.pending', 1)
        ->assertJsonPath('data.backlog.failed', 1)
        ->assertJsonPath('data.backlog.synced', 1)
        ->assertJsonPath('data.backlog.unsynced', 2);
});

it('exposes failure reasons for failed assets in the status response', function (): void {
    $token = mediaStorageToken();

    mediaStorageAsset([
        'remote_sync_status' => 'failed',
        'remote_sync_error' => 'SignatureDoesNotMatch',
        'original_name' => 'broken.mp4',
    ]);

    $this->withToken($token)->getJson('/api/v1/admin/settings/media-storage')
        ->assertOk()
        ->assertJsonPath('data.failures.0.name', 'broken.mp4')
        ->assertJsonPath('data.failures.0.error', 'SignatureDoesNotMatch');
});

it('never leaks the raw secret value in the status response', function (): void {
    $token = mediaStorageToken();
    $settings = app(MediaStorageSettings::class);
    $settings->remote_secret = 'leak-me-not';
    $settings->save();

    $response = $this->withToken($token)->getJson('/api/v1/admin/settings/media-storage');

    expect($response->getContent())->not->toContain('leak-me-not');
});

// ─── Update ───────────────────────────────────────────────────────────────

it('updates media storage settings', function (): void {
    $token = mediaStorageToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/media-storage', [
        'remote_enabled' => true,
        'remote_driver' => 's3',
        'remote_bucket' => 'alpha-media',
        'remote_region' => 'auto',
    ])->assertOk()
        ->assertJsonPath('data.remote_enabled', true)
        ->assertJsonPath('data.remote_bucket', 'alpha-media');

    expect(app(MediaStorageSettings::class)->remote_bucket)->toBe('alpha-media');
});

it('stores remote secrets encrypted and never in plaintext', function (): void {
    $token = mediaStorageToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/media-storage', [
        'remote_secret' => 'r2-secret-xyz',
    ])->assertOk();

    $raw = DB::table('settings')
        ->where('group', 'media')
        ->where('name', 'remote_secret')
        ->value('payload');

    expect($raw)->not->toContain('r2-secret-xyz');
    expect(app(MediaStorageSettings::class)->remote_secret)->toBe('r2-secret-xyz');
});

it('does not overwrite an existing remote secret when masked', function (): void {
    $token = mediaStorageToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/media-storage', [
        'remote_secret' => 'keep-this-secret',
    ])->assertOk();

    $this->withToken($token)->putJson('/api/v1/admin/settings/media-storage', [
        'remote_secret' => '********',
        'remote_bucket' => 'new-bucket',
    ])->assertOk();

    expect(app(MediaStorageSettings::class)->remote_secret)->toBe('keep-this-secret');
    expect(app(MediaStorageSettings::class)->remote_bucket)->toBe('new-bucket');
});

it('masks secrets in the update response', function (): void {
    $token = mediaStorageToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/media-storage', [
        'remote_key' => 'AKIANEW',
    ])->assertOk()
        ->assertJsonPath('data.remote_key', '********')
        ->assertJsonPath('data.remote_key_configured', true);
});

it('rejects an invalid remote driver', function (): void {
    $token = mediaStorageToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/media-storage', [
        'remote_driver' => 'ftp',
    ])->assertStatus(422);
});

// ─── Connection test ────────────────────────────────────────────────────

it('fails the connection test when credentials are missing', function (): void {
    $token = mediaStorageToken();

    $this->withToken($token)->postJson('/api/v1/admin/settings/media-storage/test', [
        'remote_bucket' => 'alpha-media',
        // no key/secret saved or submitted
    ])->assertStatus(422);
});

// ─── Sync now ─────────────────────────────────────────────────────────────

it('rejects sync now when remote is disabled', function (): void {
    $token = mediaStorageToken();
    app(MediaStorageSettings::class)->remote_enabled = false;

    $this->withToken($token)->postJson('/api/v1/admin/settings/media-storage/sync')
        ->assertStatus(422);
});

it('starts sync and dispatches mirror jobs for the backlog when remote enabled', function (): void {
    $token = mediaStorageToken();
    app(MediaStorageSettings::class)->remote_enabled = true;
    Queue::fake();
    mediaStorageAsset();

    $this->withToken($token)->postJson('/api/v1/admin/settings/media-storage/sync')
        ->assertOk();

    Queue::assertPushed(MirrorMediaToRemoteJob::class);
});

// ─── Security ─────────────────────────────────────────────────────────────

it('denies reading media storage status without a token', function (): void {
    $this->getJson('/api/v1/admin/settings/media-storage')->assertStatus(401);
});

it('denies updates for an admin lacking settings.edit', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->putJson('/api/v1/admin/settings/media-storage', [
        'remote_enabled' => true,
    ])->assertStatus(403);
});

// ─── Worker robustness: disk rebuilt from current settings ─────────────────

it('builds the remote disk from current settings (worker robustness)', function (): void {
    $s = app(MediaStorageSettings::class);
    $s->remote_driver = 's3';
    $s->remote_key = 'AKIAEXAMPLE';
    $s->remote_secret = 'secret';
    $s->remote_bucket = 'alpha-media';
    $s->remote_region = 'auto';

    config()->set('filesystems.disks.'.RemoteStorage::diskName(), null);

    RemoteStorage::configureDisk();

    $cfg = config('filesystems.disks.'.RemoteStorage::diskName());
    expect($cfg)->not->toBeNull();
    expect($cfg['driver'])->toBe('s3');
    expect($cfg['bucket'])->toBe('alpha-media');
});

it('skips building the remote disk when credentials are incomplete', function (): void {
    $s = app(MediaStorageSettings::class);
    $s->remote_driver = 's3';
    $s->remote_key = ''; // incomplete
    $s->remote_bucket = '';

    // لا يُبطِل Storage::fake — حماية الاختبارات
    Storage::fake(RemoteStorage::diskName());

    RemoteStorage::configureDisk();

    // الـ fake لا يزال قائماً (لم يُمسح)
    Storage::disk(RemoteStorage::diskName())->put('probe.txt', 'ok');
    Storage::disk(RemoteStorage::diskName())->assertExists('probe.txt');
});
