<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use App\Settings\ThirdPartySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function integrationsAdminToken(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Read / masking ────────────────────────────────────────────────────

it('exposes integrations in third_party with masked secrets', function (): void {
    [, $token] = integrationsAdminToken();

    $this->withToken($token)->getJson('/api/v1/admin/settings/third_party')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'integrations' => [
                    'sportmonks' => ['enabled', 'base_url', 'api_key', 'api_key_configured'],
                    'openweather' => ['enabled', 'base_url', 'units', 'default_language', 'api_key', 'api_key_configured'],
                ],
            ],
        ]);
});

// ─── Update + encryption ───────────────────────────────────────────────

it('saves integration settings with encrypted api keys', function (): void {
    [, $token] = integrationsAdminToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'sportmonks_enabled' => true,
        'sportmonks_api_key' => 'sm-secret-key',
        'sportmonks_base_url' => 'https://api.sportmonks.com/v3',
        'openweather_enabled' => true,
        'openweather_api_key' => 'ow-secret-key',
        'openweather_base_url' => 'https://api.openweathermap.org/data/2.5',
        'openweather_units' => 'metric',
    ])->assertOk()
        ->assertJsonPath('data.integrations.sportmonks.api_key', '********')
        ->assertJsonPath('data.integrations.sportmonks.api_key_configured', true);

    expect(app(ThirdPartySettings::class)->sportmonks_api_key)->toBe('sm-secret-key');
    expect(app(ThirdPartySettings::class)->openweather_api_key)->toBe('ow-secret-key');

    foreach (['sportmonks_api_key', 'openweather_api_key'] as $name) {
        $raw = DB::table('settings')->where('group', 'third_party')->where('name', $name)->value('payload');
        expect($raw)->not->toContain('secret-key');
    }
});

it('preserves existing api key when masked value resubmitted', function (): void {
    [, $token] = integrationsAdminToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'sportmonks_api_key' => 'keep-me',
    ])->assertOk();

    $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'sportmonks_api_key' => '********',
        'sportmonks_enabled' => true,
    ])->assertOk();

    expect(app(ThirdPartySettings::class)->sportmonks_api_key)->toBe('keep-me');
});

// ─── Validation ────────────────────────────────────────────────────────

it('rejects invalid integration input', function (): void {
    [, $token] = integrationsAdminToken();

    $r = $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'sportmonks_base_url' => 'not-a-url',
        'openweather_units' => 'kelvinish',
    ]);

    $r->assertStatus(422);
    assertErrorContract($r);
    $r->assertJsonStructure(['errors' => ['sportmonks_base_url', 'openweather_units']]);
});

// ─── Test connection ───────────────────────────────────────────────────

it('passes sportmonks connection test on success', function (): void {
    [, $token] = integrationsAdminToken();
    app(ThirdPartySettings::class)->fill(['sportmonks_api_key' => 'k'])->save();
    Http::fake(['api.sportmonks.com/*' => Http::response(['data' => []], 200)]);

    $this->withToken($token)->postJson('/api/v1/admin/settings/third-party/test/sportmonks')
        ->assertOk();
});

it('fails sportmonks test on http error', function (): void {
    [, $token] = integrationsAdminToken();
    app(ThirdPartySettings::class)->fill(['sportmonks_api_key' => 'bad'])->save();
    Http::fake(['api.sportmonks.com/*' => Http::response(['message' => 'unauthorized'], 401)]);

    $this->withToken($token)->postJson('/api/v1/admin/settings/third-party/test/sportmonks')
        ->assertStatus(422);
});

it('fails when sportmonks key missing', function (): void {
    [, $token] = integrationsAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/settings/third-party/test/sportmonks')
        ->assertStatus(422);
});

it('passes openweather connection test on success', function (): void {
    [, $token] = integrationsAdminToken();
    app(ThirdPartySettings::class)->fill(['openweather_api_key' => 'k'])->save();
    Http::fake(['api.openweathermap.org/*' => Http::response(['weather' => []], 200)]);

    $this->withToken($token)->postJson('/api/v1/admin/settings/third-party/test/openweather')
        ->assertOk();
});

it('fails openweather test when key missing', function (): void {
    [, $token] = integrationsAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/settings/third-party/test/openweather')
        ->assertStatus(422);
});

// ─── Security ──────────────────────────────────────────────────────────

it('denies integration test without a token', function (): void {
    $this->postJson('/api/v1/admin/settings/third-party/test/sportmonks')->assertStatus(401);
});

it('denies a public user-ability token', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $userToken = $admin->createToken('public-token', ['user'])->plainTextToken;

    $this->withToken($userToken)
        ->postJson('/api/v1/admin/settings/third-party/test/openweather')
        ->assertStatus(403);
});

it('denies an admin lacking settings.edit', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/admin/settings/third-party/test/sportmonks')
        ->assertStatus(403);
});

it('denies an inactive admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;
    $admin->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)
        ->postJson('/api/v1/admin/settings/third-party/test/sportmonks')
        ->assertStatus(403);
});
