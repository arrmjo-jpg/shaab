<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('grants an admin access to /admin/auth/me', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('editor');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/admin/auth/me');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure(['data' => ['id', 'email', 'roles', 'permissions']]);
});

it('denies a user-ability token on admin endpoint', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('editor');
    $userToken = $admin->createToken('public-token', ['user'])->plainTextToken;

    $this->withToken($userToken)
        ->getJson('/api/v1/admin/auth/me')
        ->assertStatus(403);
});

it('denies access with no token', function (): void {
    $this->getJson('/api/v1/admin/auth/me')->assertStatus(401);
});

it('denies an inactive admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('editor');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    // الحساب يصبح موقوفاً بعد إصدار الـ token
    $admin->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)
        ->getJson('/api/v1/admin/auth/me')
        ->assertStatus(403);
});
