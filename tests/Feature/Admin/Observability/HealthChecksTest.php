<?php

declare(strict_types=1);

use App\Health\Checks\MediaProcessingHealthCheck;
use App\Health\Checks\RedisProductionCheck;
use App\Health\Checks\SchedulerHealthCheck;
use App\Models\MediaAsset;
use App\Models\ScheduledTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function obsVideoAsset(string $status): MediaAsset
{
    return MediaAsset::create([
        'uuid' => 'obs-'.uniqid(),
        'disk' => 'public',
        'path' => 'assets/'.uniqid().'/source.mp4',
        'filename' => 'source.mp4',
        'original_name' => 'source.mp4',
        'extension' => 'mp4',
        'size' => 2048,
        'mime_type' => 'video/mp4',
        'processing_status' => $status,
        'visibility' => 'public',
    ]);
}

// ─── Media processing health ────────────────────────────────────────────────

it('media check is ok when nothing failed or stuck', function (): void {
    obsVideoAsset('ready');

    $result = (new MediaProcessingHealthCheck)->run();

    expect((string) $result->status)->toBe('ok');
});

it('media check fails when an asset is stuck in processing', function (): void {
    $asset = obsVideoAsset('processing');
    // تجاوز الطوابع الزمنية: اجعل updated_at قديماً (عالق).
    MediaAsset::query()->whereKey($asset->id)->update(['updated_at' => now()->subHours(2)]);

    $result = (new MediaProcessingHealthCheck)->run();

    expect((string) $result->status)->toBe('failed');
    expect($result->meta['stuck_processing'])->toBe(1);
});

it('media check warns when many transcodes failed recently', function (): void {
    config(['performance.media.failed_alert_threshold' => 2]);
    obsVideoAsset('failed');
    obsVideoAsset('failed');

    $result = (new MediaProcessingHealthCheck)->run();

    expect((string) $result->status)->toBe('warning');
    expect($result->meta['failed_24h'])->toBe(2);
});

// ─── Scheduler health ─────────────────────────────────────────────────────

it('scheduler check is ok when no critical task has failed', function (): void {
    $result = (new SchedulerHealthCheck)->run();

    // مهام لم تُشغَّل بعد تُتجاهَل — لا إنذار كاذب.
    expect((string) $result->status)->toBe('ok');
});

it('scheduler check fails when a critical task last run failed', function (): void {
    ScheduledTask::create([
        'key' => 'reels_publish_due',
        'enabled' => true,
        'last_status' => 'failed',
        'last_error' => 'boom',
        'last_run_at' => now(),
    ]);

    $result = (new SchedulerHealthCheck)->run();

    expect((string) $result->status)->toBe('failed');
    expect(collect($result->meta['problems'])->contains(
        fn (string $p): bool => str_contains($p, 'reels_publish_due')
    ))->toBeTrue();
});

it('scheduler check fails when a critical everyMinute task is overdue', function (): void {
    ScheduledTask::create([
        'key' => 'reels_publish_due',
        'enabled' => true,
        'last_status' => 'success',
        'last_run_at' => now()->subHour(), // متأخّر كثيراً عن دورية الدقيقة
    ]);

    $result = (new SchedulerHealthCheck)->run();

    expect((string) $result->status)->toBe('failed');
});

// ─── Redis production enforcement ───────────────────────────────────────────

it('redis check passes in non-production regardless of drivers', function (): void {
    app()['env'] = 'local';
    config(['queue.default' => 'database', 'cache.default' => 'database']);

    $result = (new RedisProductionCheck)->run();

    expect((string) $result->status)->toBe('ok');
});

it('redis check fails in production when queue or cache is not redis', function (): void {
    app()['env'] = 'production';
    config(['queue.default' => 'database', 'cache.default' => 'redis']);

    $result = (new RedisProductionCheck)->run();

    expect((string) $result->status)->toBe('failed');
    expect(collect($result->meta['problems'])->contains(
        fn (string $p): bool => str_contains($p, 'QUEUE_CONNECTION')
    ))->toBeTrue();
});

it('redis check passes in production when both are redis', function (): void {
    app()['env'] = 'production';
    config(['queue.default' => 'redis', 'cache.default' => 'redis']);

    $result = (new RedisProductionCheck)->run();

    expect((string) $result->status)->toBe('ok');
});
