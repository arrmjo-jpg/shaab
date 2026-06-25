<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('returns the unified success contract on login', function (): void {
    User::factory()->create([
        'email' => 'c@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'c@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['success', 'message', 'data', 'meta']);

    expect($response->json('success'))->toBeTrue();
});

it('returns the unified error contract on invalid login', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'none@example.com',
        'password' => 'bad',
    ]);

    $response->assertStatus(401)
        ->assertJsonStructure(['success', 'message', 'errors']);

    expect($response->json('success'))->toBeFalse();
});

it('returns the unified error contract on validation failure', function (): void {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonStructure(['success', 'message', 'errors' => ['email', 'password']]);

    expect($response->json('success'))->toBeFalse();
});
