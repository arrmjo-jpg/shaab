<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// كشف /me الموسّع (Slice 1 — لوحة المستخدم): is_writer + avatar (Spatie media) + bio + آخر طلب ترقية.

it('exposes is_writer, avatar, bio and latest writer_request on /me', function (): void {
    $user = User::factory()->create([
        'is_writer' => false,
        'bio' => 'كاتب طموح',
        'status' => UserStatus::Active,
    ]);
    $user->writerRequests()->create(['status' => 'pending', 'note' => 'أرغب بالكتابة']);

    $token = $user->createToken('public', ['user'])->plainTextToken;

    $res = $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

    expect($res->json('data.is_writer'))->toBeFalse();
    expect($res->json('data.bio'))->toBe('كاتب طموح');
    expect($res->json('data.avatar'))->toBeNull();              // لا media مرفقة
    expect($res->json('data.writer_request.status'))->toBe('pending');
});

it('exposes is_writer=true and null writer_request for an approved writer', function (): void {
    $user = User::factory()->create([
        'is_writer' => true,
        'status' => UserStatus::Active,
    ]);

    $token = $user->createToken('public', ['user'])->plainTextToken;

    $res = $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

    expect($res->json('data.is_writer'))->toBeTrue();
    expect($res->json('data.writer_request'))->toBeNull();
});
