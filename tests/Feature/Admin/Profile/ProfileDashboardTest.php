<?php

declare(strict_types=1);

use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function dashboardAdmin(): array
{
    $admin = User::factory()->create(['password' => Hash::make('OldStrongPass123!')]);
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('current', ['admin'])->plainTextToken];
}

// ─── Work analytics ─────────────────────────────────────────────────────────

it('returns a real analytics contract with zeroed content for a fresh user', function (): void {
    [, $token] = dashboardAdmin();

    $res = $this->withToken($token)->getJson('/api/v1/admin/profile/analytics')->assertOk();

    $res->assertJsonStructure([
        'data' => [
            'articles' => ['created', 'published', 'drafts', 'views_generated'],
            'reels' => ['created', 'published', 'drafts'],
            'media' => ['uploads'],
            'ai' => ['requests', 'tokens', 'estimated_cost'],
        ],
    ]);
    expect($res->json('data.articles.created'))->toBe(0);
    expect($res->json('data.ai.requests'))->toBe(0);
});

it('aggregates only the user own AI usage (real numbers)', function (): void {
    [$admin, $token] = dashboardAdmin();
    AiUsage::factory()->count(3)->create(['user_id' => $admin->id, 'source' => 'ai', 'tokens' => 100, 'estimated_cost' => 0.5]);
    AiUsage::factory()->create(['user_id' => $admin->id, 'source' => 'auto', 'tokens' => 999]); // مستبعَد
    AiUsage::factory()->create(['source' => 'ai', 'tokens' => 777]); // مستخدم آخر — مستبعَد

    $res = $this->withToken($token)->getJson('/api/v1/admin/profile/analytics')->assertOk();

    expect($res->json('data.ai.requests'))->toBe(3);
    expect($res->json('data.ai.tokens'))->toBe(300);
    expect((float) $res->json('data.ai.estimated_cost'))->toBe(1.5);
});

// ─── Permission visibility ────────────────────────────────────────────────

it('exposes grouped effective permissions and role badges', function (): void {
    [, $token] = dashboardAdmin();

    $res = $this->withToken($token)->getJson('/api/v1/admin/profile/permissions')->assertOk();

    $res->assertJsonStructure([
        'data' => [
            'roles' => [['name', 'display_name']],
            'is_super_admin',
            'summary' => ['roles_count', 'permissions_count', 'groups_count'],
            'groups' => [['group', 'count', 'permissions']],
        ],
    ]);
    expect($res->json('data.is_super_admin'))->toBeTrue();
    expect($res->json('data.summary.permissions_count'))->toBeGreaterThan(0);
});

it('shows fewer effective permissions for a limited role', function (): void {
    $u = User::factory()->create();
    $u->assignRole('journalist');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $res = $this->withToken($token)->getJson('/api/v1/admin/profile/permissions')->assertOk();

    expect($res->json('data.is_super_admin'))->toBeFalse();
    expect($res->json('data.roles.0.name'))->toBe('journalist');
});

// ─── Security center ──────────────────────────────────────────────────────

it('returns a security summary and reflects a password change', function (): void {
    [, $token] = dashboardAdmin();

    $before = $this->withToken($token)->getJson('/api/v1/admin/profile/security')->assertOk();
    expect($before->json('data.password_changed_at'))->toBeNull();
    expect($before->json('data.active_sessions_count'))->toBe(1);

    $this->withToken($token)->postJson('/api/v1/admin/profile/password', [
        'current_password' => 'OldStrongPass123!',
        'password' => 'BrandNewStrongPass123!',
        'password_confirmation' => 'BrandNewStrongPass123!',
    ])->assertOk();

    $after = $this->withToken($token)->getJson('/api/v1/admin/profile/security')->assertOk();
    expect($after->json('data.password_changed_at'))->not->toBeNull();
});

// ─── Revoke other sessions ────────────────────────────────────────────────

it('revokes all other sessions but keeps the current one', function (): void {
    [$admin, $token] = dashboardAdmin();
    $admin->createToken('device-2', ['admin']);
    $admin->createToken('device-3', ['admin']);
    expect($admin->tokens()->count())->toBe(3);

    $this->withToken($token)->postJson('/api/v1/admin/profile/sessions/revoke-others')
        ->assertOk()
        ->assertJsonPath('data.revoked', 2);

    expect($admin->fresh()->tokens()->count())->toBe(1);
    // الجلسة الحالية ما زالت تعمل
    $this->withToken($token)->getJson('/api/v1/admin/profile')->assertOk();
});

// ─── Activity timeline filters ────────────────────────────────────────────

it('filters the activity timeline by log_name and event', function (): void {
    [$admin, $token] = dashboardAdmin();

    activity('auth')->causedBy($admin)->event('admin_login')
        ->withProperties(['source' => 'admin_web', 'ip' => '10.0.0.1'])->log('login');
    activity('rbac')->causedBy($admin)->performedOn($admin)->event('user_roles_updated')
        ->withProperties(['old' => [], 'new' => ['editor']])->log('roles');

    $auth = $this->withToken($token)
        ->getJson('/api/v1/admin/profile/activity?filter[log_name]=auth')->assertOk();
    foreach ($auth->json('data') as $row) {
        expect($row['log_name'])->toBe('auth');
    }

    $byEvent = $this->withToken($token)
        ->getJson('/api/v1/admin/profile/activity?filter[event]=user_roles_updated')->assertOk();
    expect(collect($byEvent->json('data'))->pluck('event')->unique()->all())->toBe(['user_roles_updated']);
});

it('denies all profile dashboard endpoints without a token', function (): void {
    $this->getJson('/api/v1/admin/profile/analytics')->assertStatus(401);
    $this->getJson('/api/v1/admin/profile/permissions')->assertStatus(401);
    $this->getJson('/api/v1/admin/profile/security')->assertStatus(401);
});
