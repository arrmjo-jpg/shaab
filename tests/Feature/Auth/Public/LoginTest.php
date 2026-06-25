<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('logs in successfully', function (): void {
    User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
});

it('rejects invalid credentials', function (): void {
    User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'login@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
    assertErrorContract($response);
});

it('rejects suspended user', function (): void {
    User::factory()->suspended()->create([
        'email' => 'sus@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'sus@example.com',
        'password' => 'password123',
    ])->assertStatus(403);
});

it('rejects banned user', function (): void {
    User::factory()->banned()->create([
        'email' => 'ban@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'ban@example.com',
        'password' => 'password123',
    ])->assertStatus(403);
});

it('updates last_login_at and last_login_ip on success', function (): void {
    $user = User::factory()->create([
        'email' => 'track@example.com',
        'password' => Hash::make('password123'),
        'last_login_at' => null,
        'last_login_ip' => null,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'track@example.com',
        'password' => 'password123',
    ])->assertOk();

    $user->refresh();

    expect($user->last_login_at)->not->toBeNull();
    expect($user->last_login_ip)->not->toBeNull();
});
