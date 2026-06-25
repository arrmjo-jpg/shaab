<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\CDN\Jobs\ProcessCdnPurgeBatch;
use App\Modules\CDN\Services\CloudflareClient;
use App\Settings\CdnSettings;
use App\Support\Cache\CacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function cdnAdminToken(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('admin-token', ['admin'])->plainTextToken];
}

function configureCdn(): void
{
    $s = app(CdnSettings::class);
    $s->cdn_enabled = true;
    $s->cdn_auto_purge = true;
    $s->cdn_plan = 'pro';
    $s->cdn_api_token = 'cf-token-123';
    $s->cdn_zone_id = 'zone-abc';
    $s->save();
}

// ─── Status ────────────────────────────────────────────────────────────

it('returns cdn status with the unified contract', function (): void {
    [, $token] = cdnAdminToken();

    $response = $this->withToken($token)->getJson('/api/v1/admin/cdn/status');

    $response->assertOk();
    assertSuccessContract($response);
    $response->assertJsonStructure(['data' => ['enabled', 'configured', 'plan', 'auto_purge', 'stats']]);
});

// ─── Settings update + masked secret ───────────────────────────────────

it('updates cdn settings and masks the token', function (): void {
    [, $token] = cdnAdminToken();

    $this->withToken($token)->putJson('/api/v1/admin/cdn/settings', [
        'cdn_enabled' => true,
        'cdn_plan' => 'business',
        'cdn_api_token' => 'secret-cf-token',
        'cdn_zone_id' => 'zone-xyz',
    ])->assertOk()
        ->assertJsonPath('data.api_token', '********')
        ->assertJsonPath('data.api_token_configured', true)
        ->assertJsonPath('data.plan', 'business');

    expect(app(CdnSettings::class)->cdn_api_token)->toBe('secret-cf-token');
});

it('does not overwrite token when masked value resubmitted', function (): void {
    [, $token] = cdnAdminToken();
    configureCdn();

    $this->withToken($token)->putJson('/api/v1/admin/cdn/settings', [
        'cdn_api_token' => '********',
        'cdn_plan' => 'enterprise',
    ])->assertOk();

    expect(app(CdnSettings::class)->cdn_api_token)->toBe('cf-token-123');
});

it('stores the cdn token encrypted', function (): void {
    [, $token] = cdnAdminToken();

    $this->withToken($token)->putJson('/api/v1/admin/cdn/settings', [
        'cdn_api_token' => 'plain-secret-xyz',
    ])->assertOk();

    $raw = DB::table('settings')
        ->where('group', 'cdn')->where('name', 'cdn_api_token')->value('payload');

    expect($raw)->not->toContain('plain-secret-xyz');
});

// ─── Connection test ───────────────────────────────────────────────────

it('passes the connection test on a valid token', function (): void {
    [, $token] = cdnAdminToken();
    configureCdn();
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true], 200)]);

    $this->withToken($token)->postJson('/api/v1/admin/cdn/test')->assertOk();
});

it('fails the connection test on an invalid token', function (): void {
    [, $token] = cdnAdminToken();
    configureCdn();
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false], 401)]);

    $this->withToken($token)->postJson('/api/v1/admin/cdn/test')->assertStatus(422);
});

it('fails the connection test when token missing', function (): void {
    [, $token] = cdnAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/cdn/test')->assertStatus(422);
});

// ─── Purge ─────────────────────────────────────────────────────────────

it('purges a small payload immediately without queueing', function (): void {
    [, $token] = cdnAdminToken();
    configureCdn();
    Queue::fake();
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true], 200)]);

    $this->withToken($token)->postJson('/api/v1/admin/cdn/purge', [
        'urls' => ['https://site.test/a', 'https://site.test/b'],
    ])->assertOk();

    Http::assertSent(fn ($req) => str_contains($req->url(), '/purge_cache'));
    Queue::assertNothingPushed();
});

it('queues a large payload via the batch job', function (): void {
    [, $token] = cdnAdminToken();
    configureCdn();
    Queue::fake();

    $urls = collect(range(1, 35))->map(fn ($i) => "https://site.test/p{$i}")->all();

    $this->withToken($token)->postJson('/api/v1/admin/cdn/purge', [
        'urls' => $urls,
    ])->assertOk();

    Queue::assertPushed(ProcessCdnPurgeBatch::class);
});

it('runs the batch job and chunks against cloudflare', function (): void {
    [, $token] = cdnAdminToken();
    configureCdn();
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true], 200)]);

    // 35 رابط → دفعتان (30 + 5) — QUEUE sync ينفّذ الـ job فوراً
    $urls = collect(range(1, 35))->map(fn ($i) => "https://site.test/p{$i}")->all();

    $this->withToken($token)->postJson('/api/v1/admin/cdn/purge', ['urls' => $urls])
        ->assertOk();

    Http::assertSentCount(2);
});

it('retries a retryable failure then succeeds (CdnRetry is real)', function (): void {
    cdnAdminToken();
    configureCdn();

    Http::fakeSequence('api.cloudflare.com/*')
        ->push(['success' => false], 500) // عطل عابر قابل لإعادة المحاولة
        ->push(['success' => true], 200); // المحاولة الثانية تنجح

    $ok = (new CloudflareClient)->purge(['https://site.test/x']);

    expect($ok)->toBeTrue();
    Http::assertSentCount(2);
});

it('rate limiter blocks the call and never hits cloudflare', function (): void {
    cdnAdminToken();
    configureCdn();
    config(['cdn.rate_limit.max' => 0]); // أي نداء ممنوع
    Http::fake();

    $ok = (new CloudflareClient)->purge(['https://site.test/x']);

    expect($ok)->toBeFalse();
    Http::assertNothingSent();
});

it('rejects purge when cdn disabled', function (): void {
    [, $token] = cdnAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/cdn/purge', [
        'urls' => ['https://site.test/x'],
    ])->assertStatus(422);
});

it('rejects invalid purge payload', function (): void {
    [, $token] = cdnAdminToken();
    configureCdn();

    $this->withToken($token)->postJson('/api/v1/admin/cdn/purge', ['urls' => []])
        ->assertStatus(422);
});

it('purges everything', function (): void {
    [, $token] = cdnAdminToken();
    configureCdn();
    Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true], 200)]);

    $this->withToken($token)->postJson('/api/v1/admin/cdn/purge-all')->assertOk();

    Http::assertSent(fn ($req) => str_contains($req->url(), '/purge_cache'));
});

// ─── Cache invalidation ────────────────────────────────────────────────

it('invalidates the cdn status cache after settings update', function (): void {
    [, $token] = cdnAdminToken();

    $this->withToken($token)->getJson('/api/v1/admin/cdn/status')->assertOk();
    expect(Cache::tags(['cdn'])->has(CacheKeys::make('cdn', 'status')))->toBeTrue();

    $this->withToken($token)->putJson('/api/v1/admin/cdn/settings', [
        'cdn_plan' => 'pro',
    ])->assertOk();

    expect(Cache::tags(['cdn'])->has(CacheKeys::make('cdn', 'status')))->toBeFalse();
});

// ─── Security ──────────────────────────────────────────────────────────

it('denies cdn endpoints without a token', function (): void {
    $this->getJson('/api/v1/admin/cdn/status')->assertStatus(401);
});

it('denies a public user-ability token', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $userToken = $admin->createToken('public-token', ['user'])->plainTextToken;

    $this->withToken($userToken)->getJson('/api/v1/admin/cdn/status')->assertStatus(403);
});

it('denies an admin lacking cdn.view', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/cdn/status')->assertStatus(403);
});

it('denies purge for an admin lacking cdn.purge', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/cdn/purge', [
        'urls' => ['https://site.test/a'],
    ])->assertStatus(403);
});

it('denies an inactive admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;
    $admin->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)->getJson('/api/v1/admin/cdn/status')->assertStatus(403);
});
