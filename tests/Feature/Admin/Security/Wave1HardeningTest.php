<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/** super_admin + admin token */
function superToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

/** فاعل غير-super: دور editor ممنوح users.create/users.edit فقط */
function editorActorToken(): array
{
    $role = Role::findByName('editor', 'web');
    $role->givePermissionTo(['users.view', 'users.create', 'users.edit']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $u = User::factory()->create();
    $u->assignRole('editor');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── P0-1 / P0-2 — privilege escalation + super_admin hard lock ─────────

it('blocks a non-super admin from granting super_admin on create', function (): void {
    [, $token] = editorActorToken();

    $this->withToken($token)->postJson('/api/v1/admin/users', [
        'name' => 'Escalation Attempt',
        'email' => 'esc@alpha.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'roles' => ['super_admin'],
    ])->assertStatus(403);

    expect(User::where('email', 'esc@alpha.test')->exists())->toBeFalse();
});

it('blocks a non-super admin from granting super_admin on update', function (): void {
    [, $token] = editorActorToken();
    $victim = User::factory()->create();

    $this->withToken($token)->putJson("/api/v1/admin/users/{$victim->id}", [
        'roles' => ['super_admin'],
    ])->assertStatus(403);

    expect($victim->fresh()->hasRole('super_admin'))->toBeFalse();
});

it('blocks a non-super admin from modifying a super_admin account', function (): void {
    [, $token] = editorActorToken();
    $superTarget = User::factory()->create();
    $superTarget->assignRole('super_admin');

    $this->withToken($token)->putJson("/api/v1/admin/users/{$superTarget->id}", [
        'name' => 'Hijacked',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertStatus(403);

    expect($superTarget->fresh()->name)->not->toBe('Hijacked');
});

it('still allows a non-super admin to manage a normal user (regression)', function (): void {
    [, $token] = editorActorToken();
    $normal = User::factory()->create();

    $this->withToken($token)->putJson("/api/v1/admin/users/{$normal->id}", [
        'name' => 'Renamed OK',
    ])->assertOk();

    expect($normal->fresh()->name)->toBe('Renamed OK');
});

it('allows a super_admin to grant super_admin (regression)', function (): void {
    [, $token] = superToken();
    $target = User::factory()->create();

    $this->withToken($token)->putJson("/api/v1/admin/users/{$target->id}", [
        'roles' => ['super_admin'],
    ])->assertOk();

    expect($target->fresh()->hasRole('super_admin'))->toBeTrue();
});

// ─── P0-3 — security headers ───────────────────────────────────────────

it('emits defensive security headers on API responses', function (): void {
    [, $token] = superToken();

    $res = $this->withToken($token)->getJson('/api/v1/admin/users');

    $res->assertOk();
    $res->assertHeader('X-Content-Type-Options', 'nosniff');
    $res->assertHeader('X-Frame-Options', 'DENY');
    expect($res->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});

// ─── P0-4 — SVG / upload hardening ─────────────────────────────────────

it('rejects an SVG branding upload', function (): void {
    [, $token] = superToken();

    $this->withToken($token)->post('/api/v1/admin/settings/media/branding', [
        'logo_light' => UploadedFile::fake()->create('logo.svg', 8, 'image/svg+xml'),
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('still accepts a valid PNG branding upload (regression)', function (): void {
    [, $token] = superToken();

    $this->withToken($token)->post('/api/v1/admin/settings/media/branding', [
        'logo_light' => UploadedFile::fake()->image('logo.png'),
    ], ['Accept' => 'application/json'])->assertOk();
});

// ─── P0-5 / P0-13 — CORS + token expiration config ─────────────────────

it('does not allow wildcard CORS origin', function (): void {
    expect(config('cors.allowed_origins'))->not->toContain('*');
    expect(config('cors.supports_credentials'))->toBeFalse();
});

it('enforces an absolute Sanctum token expiration', function (): void {
    expect(config('sanctum.expiration'))->not->toBeNull();
    expect(config('sanctum.expiration'))->toBeGreaterThan(0);
});
