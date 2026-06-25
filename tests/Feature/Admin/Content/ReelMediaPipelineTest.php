<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Jobs\TranscodeVideoAssetJob;
use App\Models\MediaAsset;
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
    Queue::fake();
});

function reelMediaEditor(): User
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u;
}

// ─── Upload flow carries the processing profile (existing flow, one param) ──

it('persists the reel processing profile on an uploaded video', function (): void {
    $u = reelMediaEditor();

    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('reel.mp4', 800, 'video/mp4'),
        $u,
        'reel',
    );

    expect($asset->processing_profile)->toBe('reel');
    expect($asset->processing_status)->toBe('queued');
    Queue::assertPushed(TranscodeVideoAssetJob::class);
});

it('upgrades an existing non-reel video to the reel profile on re-upload (no duplicate asset)', function (): void {
    $u = reelMediaEditor();

    // رفع أولي بلا profile (مثلاً كفيديو مقال).
    $first = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('a.mp4', 700, 'video/mp4'),
        $u,
    );
    expect($first->processing_profile)->toBeNull();

    Queue::fake(); // عزل رصد إعادة المعالجة عن رفع المرة الأولى

    // إعادة رفع نفس المحتوى (checksum مطابق) لأجل ريل.
    $second = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('b.mp4', 700, 'video/mp4'),
        $u,
        'reel',
    );

    expect($second->id)->toBe($first->id);   // نفس الأصل — لا تكرار وسائط
    expect(MediaAsset::count())->toBe(1);
    expect($second->fresh()->processing_profile)->toBe('reel');
    expect($second->fresh()->processing_status)->toBe('queued');
    Queue::assertPushed(TranscodeVideoAssetJob::class); // أُعيدت المعالجة عبر الخط القائم
});

it('ignores the profile for image uploads', function (): void {
    $u = reelMediaEditor();

    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->image('p.jpg', 320, 480),
        $u,
        'reel',
    );

    expect($asset->processing_profile)->toBeNull();
});

// ─── Job: reel profile adds MP4 renditions + WebP thumbnail (grouped) ──────

it('produces grouped MP4 renditions + jpg/webp thumbnail for a reel video', function (): void {
    $u = reelMediaEditor();
    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('reel.mp4', 1200, 'video/mp4'),
        $u,
        'reel',
    );

    $this->mock(VideoTranscoder::class, function ($mock): void {
        $mock->shouldReceive('probe')->andReturn(['duration' => 18, 'width' => 1080, 'height' => 1920]);
        $mock->shouldReceive('poster')->andReturnUsing(function (string $src, string $out): bool {
            file_put_contents($out, str_repeat('x', 1024));

            return true;
        });
        $mock->shouldReceive('hls')->andReturnUsing(function (string $src, string $dir): array {
            @mkdir($dir.'/stream_0', 0o755, true);
            file_put_contents($dir.'/master.m3u8', "#EXTM3U\n");
            file_put_contents($dir.'/stream_0/playlist.m3u8', "#EXTM3U\n");

            return ['success' => true, 'master' => 'master.m3u8', 'variants' => ['720p' => 'stream_0/playlist.m3u8']];
        });
        $mock->shouldReceive('thumbnailWebp')->andReturnUsing(function (string $jpg, string $webp): bool {
            file_put_contents($webp, str_repeat('x', 512));

            return true;
        });
        $mock->shouldReceive('renditions')->andReturnUsing(function (string $src, string $dir): array {
            @mkdir($dir, 0o755, true);
            file_put_contents($dir.'/360p.mp4', str_repeat('x', 2048));
            file_put_contents($dir.'/720p.mp4', str_repeat('x', 2048));
            file_put_contents($dir.'/master.mp4', str_repeat('x', 2048));

            return ['success' => true, 'master' => 'master.mp4', 'variants' => ['360p' => '360p.mp4', '720p' => '720p.mp4']];
        });
    });

    (new TranscodeVideoAssetJob($asset->id))->handle(app(VideoTranscoder::class));

    $fresh = $asset->fresh();
    $disk = Storage::disk('uploads');
    $dir = 'assets/'.$fresh->uuid;

    expect($fresh->processing_status)->toBe('ready');

    // المخرجات مجمّعة flat بجوار الأصل (لا مجلدات لكل دقّة).
    expect($disk->exists("{$dir}/360p.mp4"))->toBeTrue();
    expect($disk->exists("{$dir}/720p.mp4"))->toBeTrue();
    expect($disk->exists("{$dir}/master.mp4"))->toBeTrue();
    expect($disk->exists("{$dir}/thumbnail.webp"))->toBeTrue();
    expect($disk->exists("{$dir}/poster.jpg"))->toBeTrue();
    expect($disk->exists("{$dir}/hls/master.m3u8"))->toBeTrue();

    expect($fresh->conversions['renditions']['master'])->toContain('master.mp4');
    expect($fresh->conversions['renditions']['variants'])->toHaveKeys(['360p', '720p']);
    expect($fresh->conversions['thumbnail']['webp'])->toContain('thumbnail.webp');
    expect($fresh->conversions['thumbnail']['jpg'])->toContain('poster.jpg');
});

// ─── Default profile is untouched (no renditions / no extra ffmpeg calls) ──

it('does not produce renditions for a default (non-reel) video', function (): void {
    $u = reelMediaEditor();
    $asset = (new StoreMediaAssetAction)->handle(
        UploadedFile::fake()->create('news.mp4', 1000, 'video/mp4'),
        $u, // لا profile
    );

    // الموك لا يعرّف renditions/thumbnailWebp — لو استدعاها الـ job يفشل الاختبار.
    $this->mock(VideoTranscoder::class, function ($mock): void {
        $mock->shouldReceive('probe')->andReturn(['duration' => 30, 'width' => 1280, 'height' => 720]);
        $mock->shouldReceive('poster')->andReturnUsing(function (string $src, string $out): bool {
            file_put_contents($out, str_repeat('x', 1024));

            return true;
        });
        $mock->shouldReceive('hls')->andReturnUsing(function (string $src, string $dir): array {
            @mkdir($dir.'/stream_0', 0o755, true);
            file_put_contents($dir.'/master.m3u8', "#EXTM3U\n");
            file_put_contents($dir.'/stream_0/playlist.m3u8', "#EXTM3U\n");

            return ['success' => true, 'master' => 'master.m3u8', 'variants' => ['720p' => 'stream_0/playlist.m3u8']];
        });
    });

    (new TranscodeVideoAssetJob($asset->id))->handle(app(VideoTranscoder::class));

    $fresh = $asset->fresh();
    expect($fresh->processing_status)->toBe('ready');
    expect($fresh->conversions)->not->toHaveKey('renditions');
    expect($fresh->conversions)->not->toHaveKey('thumbnail');
});
