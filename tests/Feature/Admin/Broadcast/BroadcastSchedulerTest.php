<?php

declare(strict_types=1);

use App\Actions\Admin\Broadcast\PublishDueBroadcastsAction;
use App\Models\Broadcast;
use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('transitions only due scheduled broadcasts to live (leaves future/draft untouched)', function (): void {
    $due = Broadcast::factory()->create(['status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);
    $future = Broadcast::factory()->create(['status' => 'scheduled', 'scheduled_at' => now()->addHour()]);
    $draft = Broadcast::factory()->create(); // draft, no schedule

    $count = (new PublishDueBroadcastsAction)->handle();

    expect($count)->toBe(1);
    expect($due->fresh()->status->value)->toBe('live');
    expect($due->fresh()->started_at)->not->toBeNull();
    expect($future->fresh()->status->value)->toBe('scheduled');
    expect($draft->fresh()->status->value)->toBe('draft');
});

it('is idempotent — a second run transitions nothing', function (): void {
    Broadcast::factory()->create(['status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);

    expect((new PublishDueBroadcastsAction)->handle())->toBe(1);
    expect((new PublishDueBroadcastsAction)->handle())->toBe(0);
});

it('runs the go-live-due command and is registered everyMinute as critical', function (): void {
    $this->artisan('broadcasts:go-live-due')->assertExitCode(0);

    $def = collect(SchedulerRegistry::all())->firstWhere('command', 'broadcasts:go-live-due');
    expect($def)->not->toBeNull();
    expect($def['frequency'])->toBe('everyMinute');
    expect($def['critical'])->toBeTrue();
});
