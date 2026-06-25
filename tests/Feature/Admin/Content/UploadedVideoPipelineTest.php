<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Jobs\TranscodeVideoAssetJob;
use App\Models\User;
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
    // امنع تشغيل خط HLS تلقائياً (sync queue) — نستدعي الـ job يدوياً عند اللزوم.
    Queue::fake();
});

function videoEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Upload enqueues the pipeline ────────────────────────────────────────

it('marks an uploaded video as queued and dispatches the transcode job', function (): void {
    [$u] = videoEditor();

    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('clip.mp4', 800, 'video/mp4'),
        $u,
    );

    expect($asset->processing_status)->toBe('queued');
    expect($asset->isUploadedVideo())->toBeTrue();
    Queue::assertPushed(TranscodeVideoAssetJob::class);
});

it('does not enqueue transcoding for image uploads', function (): void {
    [$u] = videoEditor();

    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->image('photo.jpg', 640, 480),
        $u,
    );

    // الصور تدخل دورة المعالجة (queued) لكنها لا تُجدوِل وظيفة ترميز الفيديو.
    expect($asset->processing_status)->toBe('queued');
    Queue::assertNotPushed(TranscodeVideoAssetJob::class);
});

// ─── Job orchestration (transcoder mocked — ffmpeg not required) ─────────

it('produces HLS + poster and marks the asset ready', function (): void {
    [$u] = videoEditor();
    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('news.mp4', 1000, 'video/mp4'),
        $u,
    );

    $this->mock(VideoTranscoder::class, function ($mock): void {
        $mock->shouldReceive('probe')->andReturn(['duration' => 42, 'width' => 1280, 'height' => 720]);
        $mock->shouldReceive('poster')->andReturnUsing(function (string $src, string $out): bool {
            file_put_contents($out, str_repeat('x', 1024)); // poster ≥ 512 bytes

            return true;
        });
        $mock->shouldReceive('hls')->andReturnUsing(function (string $src, string $dir): array {
            @mkdir($dir.'/stream_0', 0o755, true);
            file_put_contents($dir.'/master.m3u8', "#EXTM3U\n#EXT-X-VERSION:3\nstream_0/playlist.m3u8\n");
            file_put_contents($dir.'/stream_0/playlist.m3u8', "#EXTM3U\n");
            file_put_contents($dir.'/stream_0/segment_00000.ts', 'TSDATA');

            return ['success' => true, 'master' => 'master.m3u8', 'variants' => ['720p' => 'stream_0/playlist.m3u8']];
        });
    });

    (new TranscodeVideoAssetJob($asset->id))->handle(app(VideoTranscoder::class));

    $fresh = $asset->fresh();
    expect($fresh->processing_status)->toBe('ready');
    expect($fresh->duration_seconds)->toBe(42);
    expect($fresh->conversions['hls']['master'])->toContain('hls/master.m3u8');
    expect($fresh->conversions['poster']['path'])->toContain('poster.jpg');

    $disk = Storage::disk('uploads');
    expect($disk->exists($fresh->conversions['hls']['master']))->toBeTrue();
    expect($disk->exists($fresh->conversions['hls']['master']))->toBeTrue();
    expect($disk->exists($fresh->conversions['poster']['path']))->toBeTrue();
});

it('marks the asset failed when transcoding fails', function (): void {
    [$u] = videoEditor();
    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('bad.mp4', 100, 'video/mp4'),
        $u,
    );

    $this->mock(VideoTranscoder::class, function ($mock): void {
        $mock->shouldReceive('probe')->andReturn(['duration' => null, 'width' => null, 'height' => null]);
        $mock->shouldReceive('poster')->andReturn(false);
        $mock->shouldReceive('hls')->andReturn(['success' => false, 'master' => null, 'variants' => []]);
    });

    (new TranscodeVideoAssetJob($asset->id))->handle(app(VideoTranscoder::class));

    expect($asset->fresh()->processing_status)->toBe('failed');
});

// ─── Resource + status polling endpoint ──────────────────────────────────

it('exposes processing lifecycle + hls + poster + duration in the resource', function (): void {
    [$u, $token] = videoEditor();
    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        $u,
    );

    $res = $this->withToken($token)->getJson("/api/v1/admin/media/{$asset->uuid}")->assertOk();

    expect($res->json('data.is_video'))->toBeTrue();
    expect($res->json('data.processing_status'))->toBe('queued');
    expect($res->json('data'))->toHaveKeys(['hls', 'poster', 'duration']);
});

it('requires media.view to poll asset status', function (): void {
    [$u] = videoEditor();
    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        $u,
    );

    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson("/api/v1/admin/media/{$asset->uuid}")->assertForbidden();
});
