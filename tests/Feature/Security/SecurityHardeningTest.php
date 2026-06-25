<?php

declare(strict_types=1);

use App\Models\User;
use App\Settings\ThirdPartySettings;
use App\Support\Media\ExternalVideoResolver;
use App\Support\Security\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ─── SafeUrl: SSRF / internal-host guard ────────────────────────────────────

it('accepts a public https url', function (): void {
    expect(SafeUrl::isPublicHttps('https://cdn.example.com/bucket'))->toBeTrue();
});

it('rejects non-https, loopback, private and link-local hosts', function (string $url): void {
    expect(SafeUrl::isPublicHttps($url))->toBeFalse();
})->with([
    'http (no tls)' => 'http://cdn.example.com',
    'localhost' => 'https://localhost/x',
    'loopback v4' => 'https://127.0.0.1/x',
    'metadata' => 'https://169.254.169.254/latest/meta-data',
    'private 10' => 'https://10.0.0.5/x',
    'private 192' => 'https://192.168.1.1/x',
    'private 172' => 'https://172.16.5.5/x',
    'internal tld' => 'https://db.internal/x',
    'decimal ip (127.0.0.1)' => 'https://2130706433/x',
    'hex ip (127.0.0.1)' => 'https://0x7f000001/x',
    'octal segment (127.0.0.1)' => 'https://0177.0.0.1/x',
    'cgnat 100.64/10' => 'https://100.64.0.1/x',
    'ipv6 ula' => 'https://[fd00::1]/x',
    'ipv4-mapped ipv6 loopback' => 'https://[::ffff:127.0.0.1]/x',
    'short-form ipv4' => 'https://127.1/x',
    'empty' => '',
]);

// ─── directMp4 host validation (content embeds) ─────────────────────────────

it('resolves a public https mp4 but rejects an internal one', function (): void {
    expect(ExternalVideoResolver::resolve('https://cdn.example.com/clip.mp4'))->not->toBeNull();
    expect(ExternalVideoResolver::resolve('http://cdn.example.com/clip.mp4'))->toBeNull();
    expect(ExternalVideoResolver::resolve('https://127.0.0.1/clip.mp4'))->toBeNull();
    expect(ExternalVideoResolver::resolve('https://192.168.0.10/clip.mp4'))->toBeNull();
});

// ─── Gemini API key kept out of the query string ────────────────────────────

it('sends the Gemini key in a header, never in the query string', function (): void {
    seedRoles();
    app(ThirdPartySettings::class)->fill([
        'ai_enabled' => true,
        'ai_provider' => 'gemini',
        'gemini_api_key' => 'g-secret-test',
        'gemini_model' => 'gemini-2.0-flash',
    ])->save();

    Http::fake([
        '*generativelanguage*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['excerpt' => 'ملخّص.'])]]]]],
        ], 200),
    ]);

    $u = User::factory()->create();
    $u->assignRole('super_admin');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/ai/excerpt', [
        'body' => 'متن طويل بما يكفي لتوليد ملخّص جيد ومفيد للقارئ.',
    ])->assertOk();

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('x-goog-api-key', 'g-secret-test')
            && ! str_contains($request->url(), 'key=')
            && ! str_contains($request->url(), 'g-secret-test');
    });
});

// ─── Reset-password throttling (public + admin) ─────────────────────────────

it('throttles public reset-password attempts', function (): void {
    // الحدّ: 5 محاولات/15 دقيقة لكل IP → السادسة تُرفض 429.
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'x', 'email' => 'a@b.c', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);
    }

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'x', 'email' => 'a@b.c', 'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertStatus(429);
});

it('throttles admin reset-password attempts more strictly', function (): void {
    // الحدّ: 3 محاولات/15 دقيقة لكل IP → الرابعة تُرفض 429.
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/admin/auth/reset-password', [
            'token' => 'x', 'email' => 'a@b.c', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);
    }

    $this->postJson('/api/v1/admin/auth/reset-password', [
        'token' => 'x', 'email' => 'a@b.c', 'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertStatus(429);
});
