<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Cache\CacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** مدير super_admin (يملك permissions.view) + token إداري */
function permsAdminToken(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Listing ───────────────────────────────────────────────────────────

it('lists permissions grouped with the unified contract', function (): void {
    [, $token] = permsAdminToken();

    $response = $this->withToken($token)->getJson('/api/v1/admin/permissions');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure([
        'data' => [['group', 'items' => [['name', 'display_name', 'description']]]],
    ]);
});

it('lists permission groups as real entities with counts and permissions', function (): void {
    [, $token] = permsAdminToken();

    $response = $this->withToken($token)->getJson('/api/v1/admin/permission-groups');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure([
        'data' => [[
            'id', 'slug', 'display_name', 'description', 'icon',
            'sort_order', 'is_system', 'permissions_count',
            'permissions' => [['name', 'display_name', 'description']],
        ]],
    ]);

    $userManagement = collect($response->json('data'))->firstWhere('slug', 'user_management');
    expect($userManagement['permissions_count'])->toBe(9);
    expect($userManagement['is_system'])->toBeTrue();
});

// ─── Cache behavior ────────────────────────────────────────────────────

it('caches the grouped permissions response intentionally', function (): void {
    [, $token] = permsAdminToken();

    expect(Cache::tags(['rbac'])->has(CacheKeys::permissionsGrouped()))->toBeFalse();

    $this->withToken($token)->getJson('/api/v1/admin/permissions')->assertOk();

    expect(Cache::tags(['rbac'])->has(CacheKeys::permissionsGrouped()))->toBeTrue();
});

it('caches the permission groups response intentionally', function (): void {
    [, $token] = permsAdminToken();

    expect(Cache::tags(['rbac'])->has(CacheKeys::permissionGroups()))->toBeFalse();

    $this->withToken($token)->getJson('/api/v1/admin/permission-groups')->assertOk();

    expect(Cache::tags(['rbac'])->has(CacheKeys::permissionGroups()))->toBeTrue();
});

it('invalidates permissions cache when a role syncs permissions', function (): void {
    [, $token] = permsAdminToken();

    // تعبئة الكاش
    $this->withToken($token)->getJson('/api/v1/admin/permissions')->assertOk();
    expect(Cache::tags(['rbac'])->has(CacheKeys::permissionsGrouped()))->toBeTrue();

    // إنشاء دور مع مزامنة صلاحيات يبطل الكاش صراحةً
    $this->withToken($token)->postJson('/api/v1/admin/roles', [
        'name' => 'cache_buster', 'display_name' => 'مبطل الكاش',
        'permissions' => ['users.view'],
    ])->assertCreated();

    expect(Cache::tags(['rbac'])->has(CacheKeys::permissionsGrouped()))->toBeFalse();
});

// ─── Security ──────────────────────────────────────────────────────────

it('denies permissions endpoint without a token', function (): void {
    $this->getJson('/api/v1/admin/permissions')->assertStatus(401);
    $this->getJson('/api/v1/admin/permission-groups')->assertStatus(401);
});

it('denies a public user-ability token', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $userToken = $admin->createToken('public-token', ['user'])->plainTextToken;

    $this->withToken($userToken)->getJson('/api/v1/admin/permissions')->assertStatus(403);
});

it('denies an admin lacking permissions.view', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer'); // بلا permissions.view في الـ seeder
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/permissions')->assertStatus(403);
    $this->withToken($token)->getJson('/api/v1/admin/permission-groups')->assertStatus(403);
});

it('denies an inactive admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $admin->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)->getJson('/api/v1/admin/permissions')->assertStatus(403);
});
