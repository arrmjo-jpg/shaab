<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns generic success for an existing email', function (): void {
    User::factory()->create(['email' => 'exists@example.com']);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'exists@example.com',
    ]);

    $response->assertOk();
    assertSuccessContract($response);
});

it('returns identical success for a non-existent email (no enumeration leak)', function (): void {
    $existing = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'ghost@example.com',
    ]);

    User::factory()->create(['email' => 'real@example.com']);

    $real = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'real@example.com',
    ]);

    // نفس رمز الحالة ونفس الرسالة تماماً — لا تمييز
    expect($existing->status())->toBe($real->status());
    expect($existing->json('message'))->toBe($real->json('message'));
    expect($existing->json('success'))->toBe($real->json('success'));
});

it('rejects invalid email format', function (): void {
    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
    assertErrorContract($response);
});
