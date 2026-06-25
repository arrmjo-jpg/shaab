<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('resets the password with a valid token', function (): void {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $token = Password::createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertOk();
    assertSuccessContract($response);

    $user->refresh();
    expect(Hash::check('new-password123', $user->password))->toBeTrue();
});

it('revokes existing sanctum tokens and rotates remember_token on reset', function (): void {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('old-password'),
        'remember_token' => 'stale-remember',
    ]);
    $user->createToken('device-1', ['user']);
    expect($user->tokens()->count())->toBe(1);

    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(0);
    expect($user->fresh()->remember_token)->not->toBe('stale-remember');
});

it('rejects an invalid token', function (): void {
    User::factory()->create([
        'email' => 'reset@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'totally-invalid-token',
        'email' => 'reset@example.com',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertStatus(422);
    assertErrorContract($response);
});
