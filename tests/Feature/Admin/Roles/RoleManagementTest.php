<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** مدير بكامل الصلاحيات (super_admin) + token إداري */
function superAdminToken(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Listing ───────────────────────────────────────────────────────────

it('lists roles paginated with the unified contract', function (): void {
    [, $token] = superAdminToken();

    $response = $this->withToken($token)->getJson('/api/v1/admin/roles');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure([
        'data', 'meta' => ['pagination' => ['total', 'per_page', 'current_page', 'total_pages']],
    ]);
});

it('uses pagination defaults from config/performance', function (): void {
    [, $token] = superAdminToken();

    $response = $this->withToken($token)->getJson('/api/v1/admin/roles');

    expect($response->json('meta.pagination.per_page'))
        ->toBe((int) config('performance.pagination.default'));
});

it('clamps per_page to the configured max', function (): void {
    [, $token] = superAdminToken();

    $response = $this->withToken($token)->getJson('/api/v1/admin/roles?per_page=99999');

    expect($response->json('meta.pagination.per_page'))
        ->toBe((int) config('performance.pagination.max'));
});

// ─── Create ────────────────────────────────────────────────────────────

it('creates a role and syncs permissions', function (): void {
    [, $token] = superAdminToken();

    $response = $this->withToken($token)->postJson('/api/v1/admin/roles', [
        'name' => 'content_lead',
        'display_name' => 'قائد المحتوى',
        'description' => 'يدير فريق المحتوى',
        'permissions' => ['articles.view', 'articles.publish'],
    ]);

    $response->assertCreated();
    assertSuccessContract($response);

    $role = Role::findByName('content_lead', 'web');
    expect($role->hasPermissionTo('articles.publish'))->toBeTrue();
});

it('rejects a duplicate role name', function (): void {
    [, $token] = superAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/roles', [
        'name' => 'editor', 'display_name' => 'محرر مكرر',
    ])->assertStatus(422);
});

it('rejects an invalid role name format', function (): void {
    [, $token] = superAdminToken();

    $response = $this->withToken($token)->postJson('/api/v1/admin/roles', [
        'name' => 'Invalid Name', 'display_name' => 'خطأ',
    ]);

    $response->assertStatus(422);
    assertErrorContract($response);
});

it('invalidates the roles list cache after creation', function (): void {
    [, $token] = superAdminToken();

    $before = $this->withToken($token)->getJson('/api/v1/admin/roles')
        ->json('meta.pagination.total');

    $this->withToken($token)->postJson('/api/v1/admin/roles', [
        'name' => 'fresh_role', 'display_name' => 'دور جديد',
    ])->assertCreated();

    $after = $this->withToken($token)->getJson('/api/v1/admin/roles')
        ->json('meta.pagination.total');

    expect($after)->toBe($before + 1);
});

// ─── Show / Update ─────────────────────────────────────────────────────

it('shows a role with grouped permissions', function (): void {
    [, $token] = superAdminToken();
    $role = Role::findByName('editor', 'web');
    $role->syncPermissions(['articles.view', 'articles.publish']);

    $response = $this->withToken($token)->getJson("/api/v1/admin/roles/{$role->id}");

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['permissions' => [['group', 'items']]]]);
});

it('updates a role and syncs permissions', function (): void {
    [, $token] = superAdminToken();
    $role = Role::findByName('moderator', 'web');

    $this->withToken($token)->putJson("/api/v1/admin/roles/{$role->id}", [
        'display_name' => 'مشرف محدّث',
        'permissions' => ['comments.view', 'comments.approve'],
    ])->assertOk();

    $role->refresh();
    expect($role->display_name)->toBe('مشرف محدّث');
    expect($role->hasPermissionTo('comments.approve'))->toBeTrue();
});

// ─── Protected rules ───────────────────────────────────────────────────

it('blocks modifying the protected super_admin role', function (): void {
    [, $token] = superAdminToken();
    $superAdmin = Role::findByName('super_admin', 'web');

    $this->withToken($token)->putJson("/api/v1/admin/roles/{$superAdmin->id}", [
        'permissions' => ['users.view'],
    ])->assertStatus(403);
});

it('blocks renaming the super_admin role', function (): void {
    [, $token] = superAdminToken();
    $superAdmin = Role::findByName('super_admin', 'web');

    $this->withToken($token)->putJson("/api/v1/admin/roles/{$superAdmin->id}", [
        'name' => 'foo',
    ])->assertStatus(403);

    expect(Role::where('name', 'super_admin')->exists())->toBeTrue();
});

it('blocks deleting the super_admin role', function (): void {
    [, $token] = superAdminToken();
    $superAdmin = Role::findByName('super_admin', 'web');

    $this->withToken($token)->deleteJson("/api/v1/admin/roles/{$superAdmin->id}")
        ->assertStatus(403);
});

it('prevents self privilege lockout when removing own critical access', function (): void {
    $manager = Role::create(['name' => 'rbac_manager', 'guard_name' => 'web', 'display_name' => 'مدير الصلاحيات']);
    $manager->syncPermissions(['roles.view', 'roles.edit']);

    $actor = User::factory()->create();
    $actor->assignRole('rbac_manager');
    $token = $actor->createToken('admin-token', ['admin'])->plainTextToken;

    // محاولة سحب roles.edit من دوره
    $this->withToken($token)->putJson("/api/v1/admin/roles/{$manager->id}", [
        'permissions' => ['roles.view'],
    ])->assertStatus(403);
});

it('prevents deleting a role the actor currently holds', function (): void {
    $temp = Role::create(['name' => 'temp_admin', 'guard_name' => 'web', 'display_name' => 'مؤقت']);
    $temp->syncPermissions(['roles.view', 'roles.delete']);

    $actor = User::factory()->create();
    $actor->assignRole('temp_admin');
    $token = $actor->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->deleteJson("/api/v1/admin/roles/{$temp->id}")
        ->assertStatus(403);
});

it('deletes an unheld role successfully', function (): void {
    [, $token] = superAdminToken();
    $disposable = Role::create(['name' => 'disposable', 'guard_name' => 'web', 'display_name' => 'قابل للحذف']);

    $this->withToken($token)->deleteJson("/api/v1/admin/roles/{$disposable->id}")
        ->assertOk();

    expect(Role::where('name', 'disposable')->exists())->toBeFalse();
});

// ─── Security / Authorization ──────────────────────────────────────────

it('denies access without a token', function (): void {
    $this->getJson('/api/v1/admin/roles')->assertStatus(401);
});

it('denies a public user-ability token', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $userToken = $admin->createToken('public-token', ['user'])->plainTextToken;

    $this->withToken($userToken)->getJson('/api/v1/admin/roles')->assertStatus(403);
});

it('denies an admin lacking the roles permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer'); // لا يملك roles.view في الـ seeder
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/roles')->assertStatus(403);
});

it('denies an inactive admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $admin->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)->getJson('/api/v1/admin/roles')->assertStatus(403);
});

it('returns the unified error contract on validation failure', function (): void {
    [, $token] = superAdminToken();

    $response = $this->withToken($token)->postJson('/api/v1/admin/roles', []);

    $response->assertStatus(422);
    assertErrorContract($response);
    $response->assertJsonStructure(['errors' => ['name', 'display_name']]);
});
