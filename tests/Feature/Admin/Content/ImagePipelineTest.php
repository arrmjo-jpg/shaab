<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Jobs\GenerateMediaAssetConversionsJob;
use App\Models\MediaAsset;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Support\Media\WatermarkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    config(['media-library.disk_name' => 'uploads']);
});

function imgEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function makeImageAsset(int $w = 640, int $h = 480, string $status = 'failed'): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'disk' => 'uploads',
        'path' => 'assets/'.Str::uuid().'/img.jpg',
        'filename' => 'img.jpg',
        'original_name' => 'img.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1000,
        'width' => $w,
        'height' => $h,
        'processing_status' => $status,
        'visibility' => 'public',
    ]);
}

// ─── Processing lifecycle for images ─────────────────────────────────────

it('queues image derivative generation on upload', function (): void {
    Queue::fake();
    [$u] = imgEditor();

    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->image('photo.jpg', 800, 600),
        $u,
    );

    expect($asset->processing_status)->toBe('queued');
    Queue::assertPushed(GenerateMediaAssetConversionsJob::class);
});

it('produces thumb + medium and marks the image ready', function (): void {
    [$u] = imgEditor(); // sync queue → job runs inline

    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->image('photo.jpg', 800, 600),
        $u,
    );

    $fresh = $asset->fresh();
    expect($fresh->processing_status)->toBe('ready');
    expect($fresh->conversions)->toHaveKeys(['thumb', 'medium']);
});

// ─── Watermark settings (incl. position) flow through ─────────────────────

it('honors all watermark settings including position', function (): void {
    app(GeneralSettings::class)->fill([
        'watermark_enabled' => true,
        'watermark_image' => 'branding/watermarks/wm.png',
        'watermark_position' => 'top-right',
        'watermark_opacity' => 70,
        'watermark_width' => 120,
        'watermark_margin' => 24,
    ])->save();

    $cfg = WatermarkSettings::current();

    expect($cfg)->not->toBeNull();
    expect($cfg['position'])->toBe('top-right');
    expect($cfg['opacity'])->toBe(70);
    expect($cfg['width'])->toBe(120);
    expect($cfg['margin'])->toBe(24);
});

it('returns null watermark config when disabled', function (): void {
    expect(WatermarkSettings::current())->toBeNull();
});

// ─── Retroactive regeneration ────────────────────────────────────────────

it('regenerates derivatives for all image library assets', function (): void {
    Queue::fake();
    [, $token] = imgEditor();

    makeImageAsset(640, 480);
    makeImageAsset(641, 480);
    // external video must be ignored by image regeneration
    MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'external', 'disk' => 'external', 'path' => '',
        'filename' => '', 'original_name' => 'yt', 'mime_type' => 'video/external', 'extension' => '',
        'size' => 0, 'provider' => 'youtube', 'embed_url' => 'https://x/embed/1', 'visibility' => 'public',
    ]);

    $res = $this->withToken($token)->postJson('/api/v1/admin/media/regenerate-derivatives')->assertOk();

    expect($res->json('data.queued'))->toBe(2);
    Queue::assertPushed(GenerateMediaAssetConversionsJob::class, 2);
});

it('requires settings.edit to regenerate derivatives', function (): void {
    $user = User::factory()->create();
    $user->assignRole('journalist'); // editorial, no settings.edit
    $token = $user->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/media/regenerate-derivatives')->assertForbidden();
});

// ─── Retry failed processing ─────────────────────────────────────────────

it('reprocesses a failed image asset', function (): void {
    Queue::fake();
    [, $token] = imgEditor();
    $asset = makeImageAsset(640, 480, 'failed');

    $res = $this->withToken($token)->postJson("/api/v1/admin/media/{$asset->uuid}/reprocess")->assertOk();

    expect($res->json('data.processing_status'))->toBe('queued');
    expect($asset->fresh()->processing_status)->toBe('queued');
    Queue::assertPushed(GenerateMediaAssetConversionsJob::class);
});

it('rejects reprocessing an external video', function (): void {
    Queue::fake();
    [, $token] = imgEditor();
    $external = MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'external', 'disk' => 'external', 'path' => '',
        'filename' => '', 'original_name' => 'yt', 'mime_type' => 'video/external', 'extension' => '',
        'size' => 0, 'provider' => 'youtube', 'embed_url' => 'https://x/embed/1', 'visibility' => 'public',
    ]);

    $this->withToken($token)->postJson("/api/v1/admin/media/{$external->uuid}/reprocess")
        ->assertStatus(422)
        ->assertJsonPath('errors', []);
});

it('requires media.upload to reprocess', function (): void {
    $asset = makeImageAsset();
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson("/api/v1/admin/media/{$asset->uuid}/reprocess")->assertForbidden();
});
