<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function npSuper(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** محرّر بصلاحيات مُمرّرة فقط — لاختبار حارس التبديل. */
function npActor(string ...$perms): string
{
    $role = Role::findByName('editor', 'web');
    if ($perms !== []) {
        $role->givePermissionTo($perms);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

it('exposes the newspaper flag to an admin without settings permission (default disabled)', function (): void {
    // محرّر يملك epapers.view فقط — يجب أن يقرأ المفتاح كي يقيّد التنقّل.
    $token = npActor('epapers.view');

    $res = $this->withToken($token)->getJson('/api/v1/admin/epapers/settings')->assertOk();

    expect($res->json('data.enabled'))->toBeFalse();
    expect($res->json('data.display_name'))->not->toBeEmpty();
});

it('lets a settings editor toggle the newspaper module on and persists it', function (): void {
    $token = npSuper();

    $this->withToken($token)->putJson('/api/v1/admin/epapers/settings', [
        'enabled' => true,
        'display_name' => 'جريدتي الرقمية',
    ])->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.display_name', 'جريدتي الرقمية');

    // ثبات عبر طلب مستقلّ.
    $this->withToken($token)->getJson('/api/v1/admin/epapers/settings')
        ->assertOk()
        ->assertJsonPath('data.enabled', true);
});

it('forbids toggling the module without settings.edit', function (): void {
    $token = npActor('epapers.view');

    $this->withToken($token)->putJson('/api/v1/admin/epapers/settings', [
        'enabled' => true,
        'display_name' => 'x',
    ])->assertStatus(403);
});

it('validates the newspaper settings payload', function (): void {
    $token = npSuper();

    $this->withToken($token)->putJson('/api/v1/admin/epapers/settings', [])->assertStatus(422);
});

it('round-trips the subscribe_url setting', function (): void {
    $token = npSuper();

    $this->withToken($token)->putJson('/api/v1/admin/epapers/settings', [
        'enabled' => true,
        'display_name' => 'جريدتي',
        'subscribe_url' => 'https://example.test/subscribe',
    ])->assertOk()->assertJsonPath('data.subscribe_url', 'https://example.test/subscribe');

    $this->withToken($token)->getJson('/api/v1/admin/epapers/settings')
        ->assertOk()->assertJsonPath('data.subscribe_url', 'https://example.test/subscribe');
});

it('rejects a non-url subscribe_url', function (): void {
    $token = npSuper();

    $this->withToken($token)->putJson('/api/v1/admin/epapers/settings', [
        'enabled' => true,
        'display_name' => 'ج',
        'subscribe_url' => 'not-a-url',
    ])->assertStatus(422);
});

it('audits the settings change as key names only (no values leaked)', function (): void {
    $token = npSuper();

    $this->withToken($token)->putJson('/api/v1/admin/epapers/settings', [
        'enabled' => true,
        'display_name' => 'UNIQUE_DISPLAY_VALUE_XYZ',
    ])->assertOk();

    $activity = Activity::query()->where('log_name', 'settings')->where('event', 'updated')->latest('id')->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['group'] ?? null)->toBe('newspaper');
    expect($activity->properties['changed'] ?? [])->toContain('enabled')->toContain('display_name');
    // أسماء المفاتيح فقط — لا تُسرَّب القيمة.
    expect(json_encode($activity->properties))->not->toContain('UNIQUE_DISPLAY_VALUE_XYZ');
});
