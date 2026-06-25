<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('revokes only the current token and keeps other tokens valid', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $tokenA = $user->createToken('public-token', ['user'])->plainTextToken;
    $tokenB = $user->createToken('public-token', ['user'])->plainTextToken;

    expect($user->tokens()->count())->toBe(2);

    // تسجيل خروج باستخدام token A
    $response = $this->withToken($tokenA)->postJson('/api/v1/auth/logout');

    $response->assertOk();
    assertSuccessContract($response);

    // token A أُلغي، token B باقٍ
    expect($user->tokens()->count())->toBe(1);

    $this->withToken($tokenB)->getJson('/api/v1/auth/me')->assertOk();
});

it('blocks unauthenticated logout', function (): void {
    $this->postJson('/api/v1/auth/logout')->assertStatus(401);
});
