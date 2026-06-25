<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('logs in an admin successfully', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('editor');

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'roles']]]);
});

it('denies a non-admin user BEFORE creating any token', function (): void {
    $user = User::factory()->create([
        'email' => 'plain@example.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('user');

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'plain@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401);
    // لم يُصدَر أي token — fail early قبل createToken
    expect($user->tokens()->count())->toBe(0);
});

it('denies an inactive (suspended) admin', function (): void {
    $admin = User::factory()->suspended()->create([
        'email' => 'sus-admin@example.com',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('editor');

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'sus-admin@example.com',
        'password' => 'password123',
    ])->assertStatus(403);

    expect($admin->tokens()->count())->toBe(0);
});

it('denies an admin whose email is not verified', function (): void {
    $admin = User::factory()->unverified()->create([
        'email' => 'unver-admin@example.com',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('editor');

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'unver-admin@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(403);
    expect($response->json('errors.code'))->toBe('email_unverified');
    expect($admin->tokens()->count())->toBe(0);
});

it('rejects wrong admin credentials', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('editor');

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong',
    ])->assertStatus(401);
});

it('issues a token with ability "admin" only', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('super_admin');

    $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password123',
    ])->assertOk();

    expect($admin->tokens()->first()->abilities)->toBe(['admin']);
});
