<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function blSuper(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function blActor(string ...$perms): string
{
    $role = Role::findByName('editor', 'web');
    if ($perms !== []) {
        $role->givePermissionTo($perms);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

// ─── Schedule (draft → scheduled) ─────────────────────────────────────────────

it('schedules a draft broadcast for a future start', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->create(); // draft

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/schedule", [
        'scheduled_at' => now()->addDay()->toISOString(),
    ])->assertOk()->assertJsonPath('data.status', 'scheduled');

    expect($b->fresh()->scheduled_at)->not->toBeNull();
});

it('rejects scheduling without a future date', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->create();

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/schedule", [
        'scheduled_at' => now()->subDay()->toISOString(),
    ])->assertStatus(422);
});

it('rejects an illegal schedule transition (live → scheduled) without mutating', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->live()->create();

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/schedule", [
        'scheduled_at' => now()->addDay()->toISOString(),
    ])->assertStatus(422);

    expect($b->fresh()->status->value)->toBe('live');
});

it('requires broadcasts.schedule', function (): void {
    $token = blActor('broadcasts.view');
    $b = Broadcast::factory()->create();

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/schedule", [
        'scheduled_at' => now()->addDay()->toISOString(),
    ])->assertStatus(403);
});

// ─── Live controls (start / offline / resume / end / fail) ────────────────────

it('starts a scheduled broadcast (→ live) and stamps started_at', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->scheduled()->create();

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/start")
        ->assertOk()->assertJsonPath('data.status', 'live');

    expect($b->fresh()->started_at)->not->toBeNull();
});

it('marks a live broadcast offline, then resumes it', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->live()->create();

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/offline")
        ->assertOk()->assertJsonPath('data.status', 'offline');

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/resume")
        ->assertOk()->assertJsonPath('data.status', 'live');
});

it('ends a live broadcast and stamps ended_at', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->live()->create();

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/end")
        ->assertOk()->assertJsonPath('data.status', 'ended');

    expect($b->fresh()->ended_at)->not->toBeNull();
});

it('fails a live broadcast with a reason snapshot', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->live()->create();

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/fail", [
        'reason' => 'المصدر غير قابل للوصول',
    ])->assertOk()->assertJsonPath('data.status', 'failed');

    expect($b->fresh()->last_health_message)->toBe('المصدر غير قابل للوصول');
});

it('requires broadcasts.control for live controls', function (): void {
    $token = blActor('broadcasts.view');
    $b = Broadcast::factory()->scheduled()->create();

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/start")->assertStatus(403);
});

// ─── Archive (terminal) + illegal transitions ────────────────────────────────

it('archives an ended broadcast', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->create(['status' => 'ended', 'ended_at' => now()->subHour()]);

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/archive")
        ->assertOk()->assertJsonPath('data.status', 'archived');
});

it('requires broadcasts.archive to archive', function (): void {
    $token = blActor('broadcasts.view');
    $b = Broadcast::factory()->create(['status' => 'ended']);

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/archive")->assertStatus(403);
});

it('rejects reviving an ended broadcast (ended → live) without mutating', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->create(['status' => 'ended']);

    $this->withToken($token)->postJson("/api/v1/admin/broadcasts/{$b->id}/start")->assertStatus(422);
    expect($b->fresh()->status->value)->toBe('ended');
});

// ─── Critical invariant: status NEVER mutated via generic CRUD update ─────────

it('does not allow status mutation through the CRUD update endpoint', function (): void {
    $token = blSuper();
    $b = Broadcast::factory()->create(); // draft

    $this->withToken($token)->putJson("/api/v1/admin/broadcasts/{$b->id}", [
        'title' => 'عنوان جديد',
        'status' => 'live', // must be ignored by the CRUD request
    ])->assertOk()->assertJsonPath('data.status', 'draft');

    expect($b->fresh()->status->value)->toBe('draft');
});
