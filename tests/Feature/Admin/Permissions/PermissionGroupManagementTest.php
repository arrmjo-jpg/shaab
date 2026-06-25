<?php

declare(strict_types=1);

use App\Models\PermissionGroup;
use App\Models\User;
use App\Support\Cache\CacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** مدير super_admin (يملك جميع صلاحيات permission-groups) + token إداري */
function pgAdminToken(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Create ────────────────────────────────────────────────────────────

it('creates a non-system permission group', function (): void {
    [, $token] = pgAdminToken();

    $response = $this->withToken($token)->postJson('/api/v1/admin/permission-groups', [
        'slug' => 'live_streaming',
        'display_name' => 'البث المباشر',
        'description' => 'إدارة البث المباشر',
        'icon' => 'Radio',
        'sort_order' => 50,
    ]);

    $response->assertCreated();
    assertSuccessContract($response);

    $group = PermissionGroup::where('slug', 'live_streaming')->first();
    expect($group)->not->toBeNull();
    expect($group->is_system)->toBeFalse();
});

it('rejects a duplicate slug', function (): void {
    [, $token] = pgAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/permission-groups', [
        'slug' => 'user_management', 'display_name' => 'مكرر',
    ])->assertStatus(422);
});

it('rejects an invalid slug format', function (): void {
    [, $token] = pgAdminToken();

    $response = $this->withToken($token)->postJson('/api/v1/admin/permission-groups', [
        'slug' => 'Invalid Slug', 'display_name' => 'خطأ',
    ]);

    $response->assertStatus(422);
    assertErrorContract($response);
});

// ─── Update ────────────────────────────────────────────────────────────

it('updates metadata of a system group but blocks slug change', function (): void {
    [, $token] = pgAdminToken();
    $group = PermissionGroup::where('slug', 'user_management')->first();

    // تعديل العرض مسموح
    $this->withToken($token)->putJson("/api/v1/admin/permission-groups/{$group->id}", [
        'display_name' => 'إدارة المستخدمين (محدّث)',
    ])->assertOk();

    expect($group->fresh()->display_name)->toBe('إدارة المستخدمين (محدّث)');

    // تغيير الـ slug لمجموعة نظامية ممنوع
    $this->withToken($token)->putJson("/api/v1/admin/permission-groups/{$group->id}", [
        'slug' => 'changed_slug',
    ])->assertStatus(403);

    expect($group->fresh()->slug)->toBe('user_management');
});

// ─── Delete ────────────────────────────────────────────────────────────

it('blocks deleting a system group', function (): void {
    [, $token] = pgAdminToken();
    $group = PermissionGroup::where('slug', 'user_management')->first();

    $this->withToken($token)->deleteJson("/api/v1/admin/permission-groups/{$group->id}")
        ->assertStatus(403);

    expect(PermissionGroup::where('slug', 'user_management')->exists())->toBeTrue();
});

it('deletes a non-system group and invalidates cache', function (): void {
    [, $token] = pgAdminToken();

    $created = $this->withToken($token)->postJson('/api/v1/admin/permission-groups', [
        'slug' => 'disposable_group', 'display_name' => 'قابلة للحذف',
    ])->json('data.id');

    // تعبئة الكاش
    $this->withToken($token)->getJson('/api/v1/admin/permission-groups')->assertOk();
    expect(Cache::tags(['rbac'])->has(CacheKeys::permissionGroups()))->toBeTrue();

    $this->withToken($token)->deleteJson("/api/v1/admin/permission-groups/{$created}")
        ->assertOk();

    expect(PermissionGroup::find($created))->toBeNull();
    expect(Cache::tags(['rbac'])->has(CacheKeys::permissionGroups()))->toBeFalse();
});

// ─── Security ──────────────────────────────────────────────────────────

it('denies permission-groups CRUD without a token', function (): void {
    $this->postJson('/api/v1/admin/permission-groups', [])->assertStatus(401);
});

it('denies an admin lacking permission-groups.create', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer'); // بلا صلاحيات إدارة المجموعات
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/permission-groups', [
        'slug' => 'x_group', 'display_name' => 'س',
    ])->assertStatus(403);
});
