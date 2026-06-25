<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function profileAdmin(): array
{
    $admin = User::factory()->create(['password' => Hash::make('OldPass123!')]);
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('current', ['admin'])->plainTextToken];
}

// ─── Show ───────────────────────────────────────────────────────────────

it('returns the self profile without a permissions payload', function (): void {
    [, $token] = profileAdmin();

    $response = $this->withToken($token)->getJson('/api/v1/admin/profile');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure([
        'data' => ['id', 'name', 'email', 'email_verified', 'is_writer', 'roles'],
    ]);
    expect($response->json('data'))->not->toHaveKey('permissions');
});

// ─── Update (whitelist) ─────────────────────────────────────────────────

it('updates only whitelisted fields and ignores privilege fields', function (): void {
    [$admin, $token] = profileAdmin();

    $this->withToken($token)->putJson('/api/v1/admin/profile', [
        'name' => 'الاسم المحدّث',
        'bio' => 'نبذة',
        'social_links' => ['facebook' => 'https://fb.com/x'],
        // حقول صلاحيات يجب تجاهلها
        'status' => 'banned',
        'is_writer' => true,
        'email' => 'hacker@example.com',
        'roles' => ['user'],
    ])->assertOk();

    $admin->refresh();
    expect($admin->name)->toBe('الاسم المحدّث');
    expect($admin->bio)->toBe('نبذة');
    expect($admin->status->value)->toBe('active');
    expect($admin->is_writer)->toBeFalse();
    expect($admin->email)->not->toBe('hacker@example.com');
    expect($admin->hasRole('super_admin'))->toBeTrue();
});

// ─── Change password ────────────────────────────────────────────────────

it('rejects a wrong current password', function (): void {
    [, $token] = profileAdmin();

    $this->withToken($token)->postJson('/api/v1/admin/profile/password', [
        'current_password' => 'WrongPass',
        'password' => 'NewStrongPass123!',
        'password_confirmation' => 'NewStrongPass123!',
    ])->assertStatus(422)->assertJsonPath('errors.current_password.0', fn ($m) => filled($m));
});

it('changes password, revokes other tokens, keeps current session', function (): void {
    [$admin, $token] = profileAdmin();
    $admin->createToken('other-device', ['admin']); // جلسة أخرى

    expect($admin->tokens()->count())->toBe(2);

    $this->withToken($token)->postJson('/api/v1/admin/profile/password', [
        'current_password' => 'OldPass123!',
        'password' => 'NewStrongPass123!',
        'password_confirmation' => 'NewStrongPass123!',
    ])->assertOk();

    expect(Hash::check('NewStrongPass123!', $admin->fresh()->password))->toBeTrue();
    // التوكن الحالي فقط نجا
    expect($admin->tokens()->count())->toBe(1);
    // الجلسة الحالية ما زالت تعمل
    $this->withToken($token)->getJson('/api/v1/admin/profile')->assertOk();
});

// ─── Activity sanitization ──────────────────────────────────────────────

it('exposes only sanitized activity context (no raw internal keys)', function (): void {
    [$admin, $token] = profileAdmin();

    activity('auth')
        ->causedBy($admin)
        ->event('password_reset_requested')
        ->withProperties([
            'source' => 'admin_web',
            'ip' => '10.0.0.9',
            'user_agent' => str_repeat('UA', 300),
            'secret_internal' => 'TOP_SECRET',
            'token' => 'leaky',
        ])
        ->log('x');

    $response = $this->withToken($token)->getJson('/api/v1/admin/profile/activity');

    $response->assertOk();
    $ctx = $response->json('data.0.context');
    expect($ctx)->toHaveKey('source');
    expect($ctx)->toHaveKey('ip');
    expect($ctx)->not->toHaveKey('secret_internal');
    expect($ctx)->not->toHaveKey('token');
    expect(strlen($ctx['user_agent']))->toBeLessThanOrEqual(180);
});

// ─── Sessions ───────────────────────────────────────────────────────────

it('lists sessions flagging the current one', function (): void {
    [$admin, $token] = profileAdmin();
    $admin->createToken('another', ['admin']);

    $response = $this->withToken($token)->getJson('/api/v1/admin/profile/sessions');

    $response->assertOk();
    $sessions = collect($response->json('data'));
    expect($sessions)->toHaveCount(2);
    expect($sessions->where('current', true))->toHaveCount(1);
});

it('revokes another session but never the current one', function (): void {
    [$admin, $token] = profileAdmin();
    $other = $admin->createToken('other', ['admin'])->accessToken;
    $currentId = $admin->tokens()->where('name', 'current')->value('id');

    // إنهاء الجلسة الحالية ممنوع
    $this->withToken($token)->deleteJson("/api/v1/admin/profile/sessions/{$currentId}")
        ->assertStatus(422);

    // إنهاء الأخرى مسموح
    $this->withToken($token)->deleteJson("/api/v1/admin/profile/sessions/{$other->id}")
        ->assertOk();

    expect($admin->tokens()->whereKey($other->id)->exists())->toBeFalse();
});

// ─── Security ───────────────────────────────────────────────────────────

it('denies profile without a token', function (): void {
    $this->getJson('/api/v1/admin/profile')->assertStatus(401);
});
