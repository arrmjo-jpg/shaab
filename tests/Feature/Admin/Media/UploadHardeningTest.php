<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Jobs\TranscodeVideoAssetJob;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Media\TranscodeProgress;
use App\Support\Media\VideoTranscoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    config(['media-library.disk_name' => 'uploads']);
    Queue::fake(); // لا نشغّل الترميز تلقائياً — نشغّله يدوياً بمحوّل مُموّه
});

function uhEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function uhVideoAsset(string $profile = 'reel'): MediaAsset
{
    [$u] = uhEditor();

    return (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('clip.mp4', 1000, 'video/mp4'),
        $u,
        $profile,
    );
}

function uhRunWithProbe(int $id, array $probe): void
{
    test()->mock(VideoTranscoder::class, function ($mock) use ($probe): void {
        $mock->shouldReceive('probe')->andReturn($probe);
    });
    (new TranscodeVideoAssetJob($id))->handle(app(VideoTranscoder::class));
}

// ─── Request layer (pre-storage) ────────────────────────────────────────────

it('rejects a reel video larger than the reel size cap', function (): void {
    [, $token] = uhEditor();

    // 200MB > سقف الريل (150MB)
    $res = $this->withToken($token)->post('/api/v1/admin/media', [
        'file' => UploadedFile::fake()->create('big.mp4', 200000, 'video/mp4'),
        'profile' => 'reel',
    ]);

    $res->assertStatus(422);
});

it('rejects an unsupported video container', function (): void {
    [, $token] = uhEditor();

    $res = $this->withToken($token)->post('/api/v1/admin/media', [
        'file' => UploadedFile::fake()->create('clip.mkv', 1000, 'video/x-matroska'),
    ]);

    $res->assertStatus(422);
});

// ─── Job layer (post-probe hardening) ────────────────────────────────────────

it('rejects a reel video exceeding the max duration', function (): void {
    $asset = uhVideoAsset('reel');

    uhRunWithProbe($asset->id, ['duration' => 9999, 'width' => 1080, 'height' => 1920]);

    $fresh = $asset->fresh();
    expect($fresh->processing_status)->toBe('failed');
    expect($fresh->metadata['processing_error'])->toBe('duration_exceeded');
});

it('rejects a video exceeding the max dimension', function (): void {
    $asset = uhVideoAsset('reel');

    uhRunWithProbe($asset->id, ['duration' => 10, 'width' => 9000, 'height' => 9000]);

    $fresh = $asset->fresh();
    expect($fresh->processing_status)->toBe('failed');
    expect($fresh->metadata['processing_error'])->toBe('dimensions_exceeded');
});

it('rejects an undecodable video (no probe stream)', function (): void {
    $asset = uhVideoAsset('reel');

    uhRunWithProbe($asset->id, ['duration' => null, 'width' => null, 'height' => null]);

    $fresh = $asset->fresh();
    expect($fresh->processing_status)->toBe('failed');
    expect($fresh->metadata['processing_error'])->toBe('undecodable');
});

it('surfaces the failure reason in the transcode progress payload', function (): void {
    $asset = uhVideoAsset('reel');
    uhRunWithProbe($asset->id, ['duration' => 9999, 'width' => 1080, 'height' => 1920]);

    $progress = TranscodeProgress::for($asset->fresh());

    expect($progress['status'])->toBe('failed');
    expect($progress['error'])->toBe('duration_exceeded');
});
