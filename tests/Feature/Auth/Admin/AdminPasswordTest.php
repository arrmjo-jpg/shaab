<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\VerifyAdminEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

it('returns generic success for admin forgot-password (no enumeration)', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole('editor');

    $known = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.com',
    ]);

    $unknown = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $known->assertOk();
    expect($known->status())->toBe($unknown->status());
    expect($known->json('message'))->toBe($unknown->json('message'));
});

it('logs the trusted X-Client-Source on forgot-password', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole('editor');

    $this->withHeaders(['X-Client-Source' => 'admin_flutter'])
        ->postJson('/api/v1/admin/auth/forgot-password', ['email' => 'admin@example.com'])
        ->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'auth')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->event)->toBe('password_reset_requested');
    expect($activity->properties['source'])->toBe('admin_flutter');
    expect($activity->properties)->toHaveKeys(['ip', 'user_agent', 'timestamp', 'requested_email']);
});

it('falls back to unknown source for a missing header', function (): void {
    User::factory()->create(['email' => 'a@example.com'])->assignRole('editor');

    $this->postJson('/api/v1/admin/auth/forgot-password', ['email' => 'a@example.com'])
        ->assertOk();

    expect(Activity::query()->latest('id')->first()->properties['source'])
        ->toBe('unknown');
});

it('falls back to unknown source for an invalid header value', function (): void {
    User::factory()->create(['email' => 'b@example.com'])->assignRole('editor');

    $this->withHeaders(['X-Client-Source' => 'evil_injection'])
        ->postJson('/api/v1/admin/auth/forgot-password', ['email' => 'b@example.com'])
        ->assertOk();

    expect(Activity::query()->latest('id')->first()->properties['source'])
        ->toBe('unknown');
});

it('includes the origin context in the reset email', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole('editor');

    $this->withHeaders(['X-Client-Source' => 'admin_web'])
        ->postJson('/api/v1/admin/auth/forgot-password', ['email' => 'admin@example.com'])
        ->assertOk();

    Notification::assertSentTo(
        $admin,
        QueuedResetPassword::class,
        function ($notification) use ($admin) {
            $mail = $notification->toMail($admin);
            $lines = implode(' ', array_merge($mail->introLines, $mail->outroLines));

            return str_contains($lines, __('auth.reset_source.admin_web'));
        }
    );
});

it('returns generic success for resend verification (no enumeration)', function (): void {
    $admin = User::factory()->unverified()->create(['email' => 'u1@example.com']);
    $admin->assignRole('editor');

    $known = $this->postJson('/api/v1/admin/auth/email/resend', ['email' => 'u1@example.com']);
    $unknown = $this->postJson('/api/v1/admin/auth/email/resend', ['email' => 'ghost@example.com']);

    $known->assertOk();
    expect($known->json('message'))->toBe($unknown->json('message'));
});

it('sends a verification notification to an eligible admin', function (): void {
    Notification::fake();
    $admin = User::factory()->unverified()->create(['email' => 'elig@example.com']);
    $admin->assignRole('editor');

    $this->postJson('/api/v1/admin/auth/email/resend', ['email' => 'elig@example.com'])
        ->assertOk();

    Notification::assertSentTo(
        $admin,
        VerifyAdminEmail::class
    );
});

it('verifies the email via a valid signed link and redirects', function (): void {
    $admin = User::factory()->unverified()->create(['email' => 'sign@example.com']);
    $admin->assignRole('editor');

    $url = URL::temporarySignedRoute(
        'admin.verification.verify',
        now()->addMinutes(60),
        ['id' => $admin->id, 'hash' => sha1($admin->email)]
    );

    $this->get($url)->assertRedirect();
    expect($admin->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects an unsigned verification link', function (): void {
    $admin = User::factory()->unverified()->create(['email' => 'nosig@example.com']);

    $this->get("/api/v1/admin/auth/email/verify/{$admin->id}/".sha1($admin->email))
        ->assertStatus(403);

    expect($admin->fresh()->email_verified_at)->toBeNull();
});

it('resets an admin password with a valid token', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('old-pass'),
    ]);
    $admin->assignRole('editor');

    $token = Password::createToken($admin);

    $this->postJson('/api/v1/admin/auth/reset-password', [
        'token' => $token,
        'email' => 'admin@example.com',
        'password' => 'NewStrongPass123!',
        'password_confirmation' => 'NewStrongPass123!',
    ])->assertOk();

    $admin->refresh();
    expect(Hash::check('NewStrongPass123!', $admin->password))->toBeTrue();
});

it('rejects admin reset with an invalid token', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole('editor');

    $this->postJson('/api/v1/admin/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => 'admin@example.com',
        'password' => 'NewStrongPass123!',
        'password_confirmation' => 'NewStrongPass123!',
    ])->assertStatus(422);
});
