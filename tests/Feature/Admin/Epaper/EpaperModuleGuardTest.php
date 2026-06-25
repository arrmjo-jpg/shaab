<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function guardSuper(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

// الوحدة معطَّلة افتراضياً (NewspaperSettings.enabled = false).

it('404s every admin epaper route while the module is disabled — even for super_admin', function (): void {
    $token = guardSuper();

    $this->withToken($token)->getJson('/api/v1/admin/epapers')->assertNotFound();
    $this->withToken($token)->postJson('/api/v1/admin/epapers', [])->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/admin/epapers/1')->assertNotFound();
    $this->withToken($token)->putJson('/api/v1/admin/epapers/1', [])->assertNotFound();
    $this->withToken($token)->patchJson('/api/v1/admin/epapers/1/status', [])->assertNotFound();
    $this->withToken($token)->postJson('/api/v1/admin/epapers/1/duplicate')->assertNotFound();
    $this->withToken($token)->deleteJson('/api/v1/admin/epapers/1')->assertNotFound();
});

it('keeps the settings endpoint reachable while the module is disabled (so it can be enabled)', function (): void {
    $token = guardSuper();

    $this->withToken($token)->getJson('/api/v1/admin/epapers/settings')
        ->assertOk()
        ->assertJsonPath('data.enabled', false);
});

it('opens the admin epaper routes once the module is enabled', function (): void {
    $token = guardSuper();

    // معطَّلة ⇒ 404
    $this->withToken($token)->getJson('/api/v1/admin/epapers')->assertNotFound();

    // فعِّل الوحدة عبر نقطة الإعدادات المُعفاة من البوابة
    $this->withToken($token)->putJson('/api/v1/admin/epapers/settings', [
        'enabled' => true,
        'display_name' => 'الجريدة الرقمية',
    ])->assertOk();

    // الآن المسارات متاحة
    $this->withToken($token)->getJson('/api/v1/admin/epapers')->assertOk();
});
