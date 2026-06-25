<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\BroadcastHealthCheck;
use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'broadcast.allowed_hosts.hls' => ['allowed.test'],
        'broadcast.health.verify_resolved_ip' => false,
        'broadcast.health.cadence.live' => 0,
    ]);
});

it('runs the health-check command and probes due broadcasts', function (): void {
    Http::fake(['*' => Http::response("#EXTM3U\n#EXT-X-VERSION:3", 200)]);
    $b = Broadcast::factory()->create([
        'status' => 'live',
        'kind' => 'live',
        'source_type' => 'hls',
        'source_url' => 'https://cdn.allowed.test/live.m3u8',
    ]);

    $this->artisan('broadcasts:health-check')->assertExitCode(0);

    expect(BroadcastHealthCheck::where('broadcast_id', $b->id)->count())->toBe(1);
});

it('is registered in the scheduler everyMinute as critical', function (): void {
    $def = collect(SchedulerRegistry::all())->firstWhere('command', 'broadcasts:health-check');

    expect($def)->not->toBeNull();
    expect($def['frequency'])->toBe('everyMinute');
    expect($def['critical'])->toBeTrue();
});
