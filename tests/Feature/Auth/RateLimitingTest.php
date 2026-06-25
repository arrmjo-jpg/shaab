<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('throttles public login after 10 attempts/minute', function (): void {
    $payload = ['email' => 'x@example.com', 'password' => 'bad'];

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/login', $payload);
    }

    $response = $this->postJson('/api/v1/auth/login', $payload);

    $response->assertStatus(429);
    assertErrorContract($response);
});

it('throttles public forgot-password after 5 attempts', function (): void {
    $payload = ['email' => 'x@example.com'];

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/forgot-password', $payload);
    }

    $this->postJson('/api/v1/auth/forgot-password', $payload)
        ->assertStatus(429);
});

it('throttles admin login more strictly after 5 attempts/minute', function (): void {
    $payload = ['email' => 'x@example.com', 'password' => 'bad'];

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/admin/auth/login', $payload);
    }

    $this->postJson('/api/v1/admin/auth/login', $payload)
        ->assertStatus(429);
});

it('throttles admin forgot-password more strictly after 3 attempts', function (): void {
    $payload = ['email' => 'x@example.com'];

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/admin/auth/forgot-password', $payload);
    }

    $this->postJson('/api/v1/admin/auth/forgot-password', $payload)
        ->assertStatus(429);
});
