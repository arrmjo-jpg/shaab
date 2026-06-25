<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('active middleware denies a suspended user', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('public-token', ['user'])->plainTextToken;

    $user->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(403);
});

it('active middleware denies a banned user', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('public-token', ['user'])->plainTextToken;

    $user->update(['status' => UserStatus::Banned]);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(403);
});

it('role middleware denies a non-admin even with an admin-ability token', function (): void {
    // مستخدم بدون دور إداري لكنه يحمل token بصلاحية admin
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/auth/me')->assertStatus(403);
});

it('abilities:user denies an admin-ability token on public endpoint', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(403);
});

it('abilities:admin denies a public token on admin endpoint', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('editor');
    $token = $admin->createToken('public-token', ['user'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/auth/me')->assertStatus(403);
});
