<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Models\ScheduledTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function opsToken(string $role = 'super_admin'): string
{
    $admin = User::factory()->create();
    $admin->assignRole($role);

    return $admin->createToken('admin-token', ['admin'])->plainTextToken;
}

function opsMedia(array $attrs = []): MediaAsset
{
    $uuid = 'ops-'.uniqid();

    return MediaAsset::create(array_merge([
        'uuid' => $uuid,
        'disk' => 'uploads',
        'path' => "assets/{$uuid}/{$uuid}.mp4",
        'filename' => "{$uuid}.mp4",
        'original_name' => 'r.mp4',
        'extension' => 'mp4',
        'size' => 10,
        'mime_type' => 'video/mp4',
        'visibility' => 'public',
    ], $attrs));
}

it('returns an aggregated operational overview', function (): void {
    $token = opsToken();

    opsMedia(['remote_sync_status' => 'pending']);
    opsMedia(['remote_sync_status' => 'failed']);
    opsMedia(['processing_status' => 'failed', 'remote_sync_status' => 'synced']);

    // stuck transcoding
    $stuck = opsMedia(['processing_status' => 'processing']);
    MediaAsset::query()->whereKey($stuck->id)->update(['updated_at' => now()->subHours(3)]);

    ScheduledTask::create([
        'key' => 'reels_publish_due',
        'enabled' => true,
        'last_status' => 'failed',
        'last_run_at' => now(),
    ]);

    // a failed queue job
    app('queue.failer')->log('database', 'media', json_encode([
        'uuid' => (string) Str::uuid(),
        'displayName' => 'X',
        'data' => ['command' => 'O:8:"stdClass":0:{}'],
    ]), new RuntimeException('boom'));

    $this->withToken($token)->getJson('/api/v1/admin/system/ops-overview')
        ->assertOk()
        ->assertJsonPath('data.queue.failed', 1)
        ->assertJsonPath('data.media.sync_pending', 1)
        ->assertJsonPath('data.media.failed_mirror', 1)
        ->assertJsonPath('data.media.unsynced', 2)
        ->assertJsonPath('data.media.stuck_transcoding', 1)
        ->assertJsonPath('data.media.failed_transcode_24h', 1)
        ->assertJsonPath('data.scheduler.failed_last_run', 1)
        ->assertJsonPath('data.scheduler.tasks', 1);
});

it('denies access without the scheduler.view permission', function (): void {
    $token = opsToken('reviewer');

    $this->withToken($token)->getJson('/api/v1/admin/system/ops-overview')->assertStatus(403);
});

it('denies access without a token', function (): void {
    $this->getJson('/api/v1/admin/system/ops-overview')->assertStatus(401);
});
