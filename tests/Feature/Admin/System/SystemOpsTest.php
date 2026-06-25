<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Cache\ReelCacheTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function opsAdminToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin-token', ['admin'])->plainTextToken;
}

function opsWeakToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('journalist'); // لا يملك صلاحيات النظام

    return $u->createToken('t', ['admin'])->plainTextToken;
}

// ─── Diagnostics ────────────────────────────────────────────────────────────

it('exposes admin-safe diagnostics to scheduler.view holders', function (): void {
    $res = $this->withToken(opsAdminToken())->getJson('/api/v1/admin/system/diagnostics')->assertOk();

    expect($res->json('data.app.laravel_version'))->toBeString();
    expect($res->json('data.app.php_version'))->toBeString();
    expect($res->json('data.maintenance.down'))->toBeBool();
    expect($res->json('data.drivers.cache'))->toBeString();
    expect($res->json('data.connectivity.database'))->toBeTrue();
});

it('never leaks secrets in diagnostics', function (): void {
    $res = $this->withToken(opsAdminToken())->getJson('/api/v1/admin/system/diagnostics')->assertOk();

    $flat = json_encode($res->json('data'));
    expect($flat)->not->toContain('password');
    expect($flat)->not->toContain('secret');
    expect($flat)->not->toContain(config('app.key'));
});

it('forbids diagnostics without scheduler.view', function (): void {
    $this->withToken(opsWeakToken())->getJson('/api/v1/admin/system/diagnostics')->assertForbidden();
});

// ─── Clear content cache ──────────────────────────────────────────────────

it('clears tagged content cache for cache.clear holders', function (): void {
    Cache::tags([ArticleCacheTags::ALL])->put('probe-a', 'x', 600);
    Cache::tags([ReelCacheTags::ALL])->put('probe-r', 'y', 600);

    expect(Cache::tags([ArticleCacheTags::ALL])->get('probe-a'))->toBe('x');

    $res = $this->withToken(opsAdminToken())->postJson('/api/v1/admin/system/cache/clear')->assertOk();

    expect($res->json('data.cleared'))->toContain(ArticleCacheTags::ALL);
    expect(Cache::tags([ArticleCacheTags::ALL])->get('probe-a'))->toBeNull();
    expect(Cache::tags([ReelCacheTags::ALL])->get('probe-r'))->toBeNull();
});

it('forbids cache clear without cache.clear permission', function (): void {
    $this->withToken(opsWeakToken())->postJson('/api/v1/admin/system/cache/clear')->assertForbidden();
});

it('audits a cache clear in the activity log', function (): void {
    $this->withToken(opsAdminToken())->postJson('/api/v1/admin/system/cache/clear')->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'system',
        'event' => 'cache_cleared',
    ]);
});
