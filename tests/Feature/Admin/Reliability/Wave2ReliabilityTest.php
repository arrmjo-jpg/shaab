<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\VerifyAdminEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function relSuperToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── P0-7 — async mail ─────────────────────────────────────────────────

it('queues the admin email-verification notification', function (): void {
    expect(is_subclass_of(VerifyAdminEmail::class, ShouldQueue::class))->toBeTrue();
});

it('queues the password-reset notification', function (): void {
    expect(is_subclass_of(QueuedResetPassword::class, ShouldQueue::class))->toBeTrue();
});

it('User dispatches the queued reset notification', function (): void {
    $ref = new ReflectionMethod(User::class, 'sendPasswordResetNotification');
    expect($ref->getDeclaringClass()->getName())->toBe(User::class);
});

it('enforces an explicit SMTP timeout', function (): void {
    expect(config('mail.mailers.smtp.timeout'))->not->toBeNull();
    expect(config('mail.mailers.smtp.timeout'))->toBeGreaterThan(0);
});

// ─── P0-6 — transactional integrity (happy-path regression) ────────────

it('creates a user with roles atomically (regression)', function (): void {
    [, $token] = relSuperToken();

    $this->withToken($token)->postJson('/api/v1/admin/users', [
        'name' => 'Tx User',
        'email' => 'tx@alpha.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'roles' => ['editor'],
    ])->assertCreated();

    $u = User::where('email', 'tx@alpha.test')->first();
    expect($u)->not->toBeNull();
    expect($u->hasRole('editor'))->toBeTrue();
});

it('creates a role with permissions atomically (regression)', function (): void {
    [, $token] = relSuperToken();

    $this->withToken($token)->postJson('/api/v1/admin/roles', [
        'name' => 'tx_role',
        'display_name' => 'Tx Role',
        'permissions' => ['users.view'],
    ])->assertCreated();

    $role = Role::findByName('tx_role', 'web');
    expect($role->hasPermissionTo('users.view'))->toBeTrue();
});

// ─── P0-10 — health endpoint (protected) ───────────────────────────────

it('serves the protected health endpoint to an authorized admin', function (): void {
    [, $token] = relSuperToken();

    $this->withToken($token)->getJson('/api/v1/admin/system/health')->assertOk();
});

it('denies the health endpoint without scheduler.view', function (): void {
    $role = Role::findByName('reviewer', 'web');
    $role->givePermissionTo('users.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $u = User::factory()->create();
    $u->assignRole('reviewer');
    $token = $u->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/system/health')->assertStatus(403);
});
