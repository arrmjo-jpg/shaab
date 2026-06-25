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

/** مدير super_admin (يملك settings.view) + token إداري */
function settingsAdminToken(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Overview ──────────────────────────────────────────────────────────

it('returns the settings overview with all three root groups', function (): void {
    [, $token] = settingsAdminToken();

    $response = $this->withToken($token)->getJson('/api/v1/admin/settings');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure(['data' => ['general', 'third_party', 'cdn']]);
});

// ─── Per group ─────────────────────────────────────────────────────────

it('returns the general settings group', function (): void {
    [, $token] = settingsAdminToken();

    $response = $this->withToken($token)->getJson('/api/v1/admin/settings/general');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonPath('data.site.site_name', 'AlphaCMS');
    $response->assertJsonStructure(['data' => ['site', 'mail', 'social', 'analytics', 'backups']]);
});

it('returns the third_party settings group', function (): void {
    [, $token] = settingsAdminToken();

    $this->withToken($token)->getJson('/api/v1/admin/settings/third_party')
        ->assertOk()
        ->assertJsonStructure(['data' => ['social_login', 'recaptcha', 'firebase', 'google_maps', 'ai', 'whatsapp', 'app_links']]);
});

it('returns the cdn settings group', function (): void {
    [, $token] = settingsAdminToken();

    $this->withToken($token)->getJson('/api/v1/admin/settings/cdn')
        ->assertOk()
        ->assertJsonPath('data.plan', 'free');
});

it('returns 404 for an unknown settings group', function (): void {
    [, $token] = settingsAdminToken();

    $this->withToken($token)->getJson('/api/v1/admin/settings/unknown')
        ->assertStatus(404);
});

// ─── Secrets masking ───────────────────────────────────────────────────

it('never leaks secret values in plaintext', function (): void {
    [, $token] = settingsAdminToken();

    $response = $this->withToken($token)->getJson('/api/v1/admin/settings/third_party');

    // الأسرار مُقنَّعة أو null، مع علم بوجودها
    expect($response->json('data.ai.openai.api_key'))->toBeIn([null, '********']);
    expect($response->json('data.ai.openai'))->toHaveKey('api_key_configured');
    expect($response->json('data.recaptcha'))->toHaveKey('secret_key_configured');
});

// ─── Cache behavior ────────────────────────────────────────────────────

it('caches each settings group intentionally', function (): void {
    [, $token] = settingsAdminToken();

    expect(Cache::tags(['settings'])->has(CacheKeys::settings('general')))->toBeFalse();

    $this->withToken($token)->getJson('/api/v1/admin/settings/general')->assertOk();

    expect(Cache::tags(['settings'])->has(CacheKeys::settings('general')))->toBeTrue();
});

// ─── Security ──────────────────────────────────────────────────────────

it('denies settings without a token', function (): void {
    $this->getJson('/api/v1/admin/settings')->assertStatus(401);
});

it('denies a public user-ability token', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $userToken = $admin->createToken('public-token', ['user'])->plainTextToken;

    $this->withToken($userToken)->getJson('/api/v1/admin/settings')->assertStatus(403);
});

it('denies an admin lacking settings.view', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer'); // بلا settings.view في الـ seeder
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/settings')->assertStatus(403);
});

it('denies an inactive admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $admin->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)->getJson('/api/v1/admin/settings')->assertStatus(403);
});
