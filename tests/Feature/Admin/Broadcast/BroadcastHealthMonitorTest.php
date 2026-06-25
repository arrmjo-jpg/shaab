<?php

declare(strict_types=1);

use App\Actions\Admin\Broadcast\MonitorBroadcastHealthAction;
use App\Health\Checks\BroadcastSourceHealthCheck;
use App\Models\Broadcast;
use App\Models\BroadcastHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'broadcast.allowed_hosts.hls' => ['allowed.test'],
        'broadcast.health.verify_resolved_ip' => false,
        'broadcast.health.cadence.live' => 0,   // always due (most tests)
        'broadcast.health.cadence.tv' => 300,
        'broadcast.health.fail_threshold' => 3,
    ]);
});

function hmBroadcast(array $attrs = []): Broadcast
{
    return Broadcast::factory()->create(array_merge([
        'status' => 'live',
        'kind' => 'live',
        'source_type' => 'hls',
        'source_url' => 'https://cdn.allowed.test/live.m3u8',
        'started_at' => now()->subMinutes(10),
    ], $attrs));
}

function hmMonitor(): array
{
    return (new MonitorBroadcastHealthAction)->handle();
}

function hmFakeManifest(): void
{
    Http::fake(['*' => Http::response("#EXTM3U\n#EXT-X-VERSION:3", 200)]);
}

function hmFakeDown(): void
{
    Http::fake(['*' => Http::response('', 503)]);
}

// ─── Anti-flapping circuit breaker ────────────────────────────────────────────

it('fails a live broadcast only after the consecutive-failure threshold', function (): void {
    hmFakeDown();
    $b = hmBroadcast();

    hmMonitor();
    expect($b->fresh()->status->value)->toBe('live'); // 1 failure

    hmMonitor();
    expect($b->fresh()->status->value)->toBe('live'); // 2 failures

    $summary = hmMonitor(); // 3rd → failed
    expect($b->fresh()->status->value)->toBe('failed');
    expect($summary['failed'])->toBe(1);
    expect($b->fresh()->last_health_message)->toBe('http_503');
});

it('does not fail on a single blip and resets the counter on a healthy probe', function (): void {
    $b = hmBroadcast();

    // تسلسل: عطل عابر ثم تعافٍ (fake واحد بتسلسل — لا يتراكم/يتداخل).
    Http::fake(['*' => Http::sequence()
        ->push('', 503)
        ->push("#EXTM3U\n#EXT-X-VERSION:3", 200)]);

    hmMonitor(); // عطل → إخفاق واحد
    expect($b->fresh()->status->value)->toBe('live');
    expect($b->fresh()->health_consecutive_failures)->toBe(1);

    hmMonitor(); // تعافٍ → تصفير العدّاد
    expect($b->fresh()->status->value)->toBe('live');
    expect($b->fresh()->health_consecutive_failures)->toBe(0);
});

// ─── Recovery (system-only failed → live) ─────────────────────────────────────

it('recovers a failed broadcast to live on a healthy probe', function (): void {
    hmFakeManifest();
    $b = hmBroadcast(['status' => 'failed']);

    $summary = hmMonitor();

    expect($b->fresh()->status->value)->toBe('live');
    expect($b->fresh()->health_consecutive_failures)->toBe(0);
    expect($summary['recovered'])->toBe(1);
});

// ─── Status discipline: only live/failed are probed ───────────────────────────

it('never probes an intentionally-offline broadcast (offline ≠ failed)', function (): void {
    hmFakeManifest();
    $b = hmBroadcast(['status' => 'offline']);

    hmMonitor();

    expect($b->fresh()->status->value)->toBe('offline');
    expect(BroadcastHealthCheck::where('broadcast_id', $b->id)->count())->toBe(0);
});

it('never probes draft/scheduled/ended/archived broadcasts', function (string $status): void {
    hmFakeManifest();
    $b = hmBroadcast(['status' => $status]);

    hmMonitor();

    expect(BroadcastHealthCheck::where('broadcast_id', $b->id)->count())->toBe(0);
})->with(['draft', 'scheduled', 'ended', 'archived']);

it('never probes non-probeable sources (youtube/external) — no auto-fail', function (): void {
    hmFakeDown();
    $b = hmBroadcast(['source_type' => 'youtube_live', 'source_url' => 'https://www.youtube.com/watch?v=x']);

    $summary = hmMonitor();

    expect($summary['checked'])->toBe(0);
    expect($b->fresh()->status->value)->toBe('live');
});

// ─── History + cadence ────────────────────────────────────────────────────────

it('records a health-check history row for a probed broadcast', function (): void {
    hmFakeManifest();
    $b = hmBroadcast();

    hmMonitor();

    $check = BroadcastHealthCheck::where('broadcast_id', $b->id)->first();
    expect($check)->not->toBeNull();
    expect($check->status)->toBe('healthy');
    expect($check->latency_ms)->not->toBeNull();
});

it('honors tiered cadence — live is due sooner than tv/radio', function (): void {
    config(['broadcast.health.cadence.live' => 60, 'broadcast.health.cadence.tv' => 300]);
    hmFakeManifest();

    $live = hmBroadcast(['kind' => 'live', 'last_health_check_at' => now()->subSeconds(90)]);
    $tv = hmBroadcast(['kind' => 'tv', 'last_health_check_at' => now()->subSeconds(90)]);

    $summary = hmMonitor();

    expect($summary['checked'])->toBe(1); // live (90s > 60s) due; tv (90s < 300s) skipped
    expect(BroadcastHealthCheck::where('broadcast_id', $live->id)->count())->toBe(1);
    expect(BroadcastHealthCheck::where('broadcast_id', $tv->id)->count())->toBe(0);
});

// ─── Platform-native ops alert (Spatie health check) ──────────────────────────

it('surfaces the count of failed broadcasts to the system health endpoint', function (): void {
    expect((string) (new BroadcastSourceHealthCheck)->run()->status)->toBe('ok');

    Broadcast::factory()->create(['status' => 'failed']);

    $result = (new BroadcastSourceHealthCheck)->run();
    expect((string) $result->status)->toBe('failed');
    expect($result->meta['failed'])->toBe(1);
});
