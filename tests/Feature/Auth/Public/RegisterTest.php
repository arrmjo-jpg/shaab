<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('registers a new user successfully', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'محمد أحمد',
        'email' => 'user@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated();
    assertSuccessContract($response);

    $response->assertJsonStructure([
        'data' => ['token', 'user' => ['id', 'name', 'email', 'status']],
    ]);

    $this->assertDatabaseHas('users', ['email' => 'user@example.com']);
});

it('rejects duplicate email', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'مستخدم',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
    assertErrorContract($response);
    $response->assertJsonPath('errors.email.0', fn ($m) => $m !== null);
});

it('assigns default role "user" on registration', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'مستخدم',
        'email' => 'role@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::where('email', 'role@example.com')->first();

    expect($user->hasRole('user'))->toBeTrue();
});

it('issues a token with ability "user" only', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'مستخدم',
        'email' => 'ability@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::where('email', 'ability@example.com')->first();
    $token = $user->tokens()->first();

    expect($token->abilities)->toBe(['user']);
});

it('defaults status to active', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'مستخدم',
        'email' => 'status@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $user = User::where('email', 'status@example.com')->first();

    expect($user->status)->toBe(UserStatus::Active);
});
