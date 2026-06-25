<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('returns the authenticated public user', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('public-token', ['user'])->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/auth/me');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonPath('data.email', $user->email);
});

it('rejects unauthenticated access with 401', function (): void {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('rejects an admin-ability token on the public user endpoint', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $adminToken = $user->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($adminToken)
        ->getJson('/api/v1/auth/me')
        ->assertStatus(403);
});
