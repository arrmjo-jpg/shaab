<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('revokes only the current admin token', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $tokenA = $admin->createToken('admin-token', ['admin'])->plainTextToken;
    $tokenB = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    expect($admin->tokens()->count())->toBe(2);

    $response = $this->withToken($tokenA)->postJson('/api/v1/admin/auth/logout');

    $response->assertOk();
    assertSuccessContract($response);

    expect($admin->tokens()->count())->toBe(1);

    // token B ما زال صالحاً
    $this->withToken($tokenB)->getJson('/api/v1/admin/auth/me')->assertOk();
});
