<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** super_admin + admin token */
function hardenSuperToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── TASK 1 — password reset session invalidation ──────────────────────────

it('revokes all sanctum tokens and rotates remember_token on admin password reset', function (): void {
    $admin = User::factory()->create([
        'email' => 'reset-admin@example.com',
        'password' => Hash::make('OldStrongPass123!'),
        'remember_token' => 'stale-remember',
    ]);
    $admin->assignRole('editor');
    $admin->createToken('device-1', ['admin']);
    $admin->createToken('device-2', ['admin']);
    expect($admin->tokens()->count())->toBe(2);

    $token = Password::createToken($admin);

    $this->postJson('/api/v1/admin/auth/reset-password', [
        'token' => $token,
        'email' => 'reset-admin@example.com',
        'password' => 'NewStrongPass123!',
        'password_confirmation' => 'NewStrongPass123!',
    ])->assertOk();

    expect($admin->fresh()->tokens()->count())->toBe(0);
    expect($admin->fresh()->remember_token)->not->toBe('stale-remember');
});

// ─── TASK 2 — admin-set password revokes target sessions ───────────────────

it('revokes target tokens and rotates remember_token when an admin sets a new password', function (): void {
    [, $token] = hardenSuperToken();
    $target = User::factory()->create(['remember_token' => 'stale-remember']);
    $target->createToken('t1', ['admin']);
    $target->createToken('t2', ['admin']);
    expect($target->tokens()->count())->toBe(2);

    $this->withToken($token)->putJson("/api/v1/admin/users/{$target->id}", [
        'password' => 'AdminSetPass123!',
        'password_confirmation' => 'AdminSetPass123!',
    ])->assertOk();

    expect($target->fresh()->tokens()->count())->toBe(0);
    expect($target->fresh()->remember_token)->not->toBe('stale-remember');
    expect(Hash::check('AdminSetPass123!', $target->fresh()->password))->toBeTrue();
});

it('does not revoke target tokens on a non-password update', function (): void {
    [, $token] = hardenSuperToken();
    $target = User::factory()->create();
    $target->createToken('t1', ['admin']);

    $this->withToken($token)->putJson("/api/v1/admin/users/{$target->id}", [
        'name' => 'Renamed Only',
    ])->assertOk();

    expect($target->fresh()->tokens()->count())->toBe(1);
});

// ─── TASK 3 — role / permission audit logging ──────────────────────────────

it('audits user role changes with actor, subject, old and new', function (): void {
    [$actor, $token] = hardenSuperToken();
    $target = User::factory()->create();
    $target->assignRole('journalist');

    $this->withToken($token)->putJson("/api/v1/admin/users/{$target->id}", [
        'roles' => ['editor', 'reviewer'],
    ])->assertOk();

    $log = Activity::query()->where('log_name', 'rbac')
        ->where('event', 'user_roles_updated')->latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($actor->id);
    expect($log->subject_id)->toBe($target->id);
    expect($log->properties['old'])->toBe(['journalist']);
    expect($log->properties['new'])->toContain('editor')->toContain('reviewer');
    expect($log->properties['removed'])->toContain('journalist');
});

it('audits roles assigned at user creation', function (): void {
    [, $token] = hardenSuperToken();

    $this->withToken($token)->postJson('/api/v1/admin/users', [
        'name' => 'New WithRoles',
        'email' => 'withroles@example.com',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
        'roles' => ['editor'],
    ])->assertCreated();

    $log = Activity::query()->where('log_name', 'rbac')
        ->where('event', 'user_roles_updated')->latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->properties['old'])->toBe([]);
    expect($log->properties['new'])->toBe(['editor']);
});

it('audits role permission grants and revokes', function (): void {
    [$actor, $token] = hardenSuperToken();
    $role = Role::create(['name' => 'temp_role', 'guard_name' => 'web', 'display_name' => 'Temp']);

    $this->withToken($token)->putJson("/api/v1/admin/roles/{$role->id}", [
        'permissions' => ['users.view', 'users.edit'],
    ])->assertOk();

    $log = Activity::query()->where('log_name', 'rbac')
        ->where('event', 'role_permissions_updated')->latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($actor->id);
    expect($log->subject_id)->toBe($role->id);
    expect($log->properties['added'])->toContain('users.view')->toContain('users.edit');
});

it('does not log an rbac event when the role set is unchanged', function (): void {
    [, $token] = hardenSuperToken();
    $target = User::factory()->create();
    $target->assignRole('editor');

    $this->withToken($token)->putJson("/api/v1/admin/users/{$target->id}", [
        'roles' => ['editor'],
    ])->assertOk();

    expect(Activity::query()->where('log_name', 'rbac')->count())->toBe(0);
});

// ─── TASK 4 — password policy hardening ────────────────────────────────────

it('rejects a weak password when creating an admin user', function (): void {
    [, $token] = hardenSuperToken();

    $this->withToken($token)->postJson('/api/v1/admin/users', [
        'name' => 'Weak',
        'email' => 'weak@example.com',
        'password' => 'weakpassword',
        'password_confirmation' => 'weakpassword',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('rejects a password under 12 chars even with all character classes', function (): void {
    [, $token] = hardenSuperToken();

    $this->withToken($token)->postJson('/api/v1/admin/users', [
        'name' => 'Short',
        'email' => 'short@example.com',
        'password' => 'Ab1!Ab1!', // 8 chars, all classes but < 12
        'password_confirmation' => 'Ab1!Ab1!',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('rejects a weak password on admin password reset', function (): void {
    $admin = User::factory()->create(['email' => 'weakreset@example.com']);
    $admin->assignRole('editor');
    $token = Password::createToken($admin);

    $this->postJson('/api/v1/admin/auth/reset-password', [
        'token' => $token,
        'email' => 'weakreset@example.com',
        'password' => 'shortpw',
        'password_confirmation' => 'shortpw',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('accepts a strong 12+ mixed password for an admin user', function (): void {
    [, $token] = hardenSuperToken();

    $this->withToken($token)->postJson('/api/v1/admin/users', [
        'name' => 'Strong',
        'email' => 'strong@example.com',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
    ])->assertCreated();
});
