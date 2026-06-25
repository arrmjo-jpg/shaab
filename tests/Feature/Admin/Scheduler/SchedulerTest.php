<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\ScheduledTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function schedAdmin(): array
{
    $a = User::factory()->create();
    $a->assignRole('super_admin');

    return [$a, $a->createToken('admin', ['admin'])->plainTextToken];
}

it('lists scheduled tasks with backend-computed fields', function (): void {
    [, $token] = schedAdmin();

    $res = $this->withToken($token)->getJson('/api/v1/admin/system/scheduler');

    $res->assertOk();
    assertSuccessContract($res);
    $res->assertJsonStructure([
        'data' => [[
            'key', 'name', 'description', 'command', 'cron', 'frequency',
            'critical', 'manual_run_allowed', 'enabled', 'last_status',
            'next_run_at', 'health',
        ]],
    ]);
    expect(collect($res->json('data'))->pluck('key'))
        ->toContain('activity_log_cleanup', 'backups_run');
});

it('shows a single task and 404s for unknown', function (): void {
    [, $token] = schedAdmin();

    $this->withToken($token)
        ->getJson('/api/v1/admin/system/scheduler/activity_log_cleanup')
        ->assertOk()
        ->assertJsonPath('data.key', 'activity_log_cleanup');

    $this->withToken($token)
        ->getJson('/api/v1/admin/system/scheduler/ghost_task')
        ->assertStatus(404);
});

it('toggles enabled and reflects health/next_run', function (): void {
    [, $token] = schedAdmin();

    $this->withToken($token)
        ->patchJson('/api/v1/admin/system/scheduler/activity_log_cleanup', [
            'enabled' => false,
            'notes' => 'paused for maintenance',
        ])
        ->assertOk()
        ->assertJsonPath('data.health', 'disabled')
        ->assertJsonPath('data.next_run_at', null);

    expect(ScheduledTask::where('key', 'activity_log_cleanup')->value('enabled'))->toBeFalse();
    // مُدقَّق عبر AuditsChanges (log_name=scheduler) + حدث updated
    expect(
        Activity::where('log_name', 'scheduler')->exists()
    )->toBeTrue();
});

it('runs a whitelisted task, records state, then enforces cooldown', function (): void {
    [, $token] = schedAdmin();

    $this->withToken($token)
        ->postJson('/api/v1/admin/system/scheduler/activity_log_cleanup/run')
        ->assertOk()
        ->assertJsonPath('data.last_status', 'success');

    expect(ScheduledTask::where('key', 'activity_log_cleanup')->value('last_run_at'))
        ->not->toBeNull();

    // التشغيل الفوري الثاني محظور بالتهدئة
    $this->withToken($token)
        ->postJson('/api/v1/admin/system/scheduler/activity_log_cleanup/run')
        ->assertStatus(429);
});

it('denies scheduler view to an admin without the permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('editor');
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/system/scheduler')->assertStatus(403);
});

it('denies manual run to an admin lacking scheduler.run', function (): void {
    // دور مخصّص: view فقط بلا run
    $role = Role::create(['name' => 'sched_viewer', 'guard_name' => 'web', 'display_name' => 'مراقب']);
    $role->syncPermissions(['scheduler.view', 'scheduler.manage']);
    $admin = User::factory()->create();
    $admin->assignRole('sched_viewer');
    $token = $admin->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/admin/system/scheduler/activity_log_cleanup/run')
        ->assertStatus(403);
});

it('denies scheduler without a token', function (): void {
    $this->getJson('/api/v1/admin/system/scheduler')->assertStatus(401);
});
