<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use App\Settings\CdnSettings;
use App\Settings\GeneralSettings;
use App\Settings\ThirdPartySettings;
use App\Support\Cache\CacheKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function settingsWriteToken(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    return [$admin, $admin->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Updates ───────────────────────────────────────────────────────────

it('updates general settings and invalidates cache', function (): void {
    [, $token] = settingsWriteToken();

    // تعبئة الكاش
    $this->withToken($token)->getJson('/api/v1/admin/settings/general')->assertOk();
    expect(Cache::tags(['settings'])->has(CacheKeys::settings('general')))->toBeTrue();

    $this->withToken($token)->putJson('/api/v1/admin/settings/general', [
        'site_name' => 'بوابة ألفا',
        'site_email' => 'info@alpha.test',
    ])->assertOk();

    // أُبطل الكاش
    expect(Cache::tags(['settings'])->has(CacheKeys::settings('general')))->toBeFalse();

    $this->withToken($token)->getJson('/api/v1/admin/settings/general')
        ->assertJsonPath('data.site.site_name', 'بوابة ألفا');
});

it('updates third_party settings', function (): void {
    [, $token] = settingsWriteToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'ai_enabled' => true,
        'ai_provider' => 'gemini',
    ])->assertOk();

    expect(app(ThirdPartySettings::class)->ai_provider)->toBe('gemini');
});

it('updates cdn settings', function (): void {
    [, $token] = settingsWriteToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/cdn', [
        'cdn_enabled' => true,
        'cdn_plan' => 'pro',
    ])->assertOk()->assertJsonPath('data.plan', 'pro');
});

// ─── Strict validation ─────────────────────────────────────────────────

it('rejects invalid general settings', function (): void {
    [, $token] = settingsWriteToken();

    $response = $this->withToken($token)->putJson('/api/v1/admin/settings/general', [
        'site_email' => 'not-an-email',
        'timezone' => 'Mars/Phobos',
        'watermark_opacity' => 250,
    ]);

    $response->assertStatus(422);
    assertErrorContract($response);
    $response->assertJsonStructure(['errors' => ['site_email', 'timezone', 'watermark_opacity']]);
});

it('enforces AI provider enum', function (): void {
    [, $token] = settingsWriteToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'ai_provider' => 'invalid_provider',
    ])->assertStatus(422);
});

it('requires recaptcha score when version is v3', function (): void {
    [, $token] = settingsWriteToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'recaptcha_version' => 'v3',
    ])->assertStatus(422);
});

// ─── Secrets ───────────────────────────────────────────────────────────

it('stores secrets encrypted and never in plaintext', function (): void {
    [, $token] = settingsWriteToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'openai_api_key' => 'sk-super-secret-123',
    ])->assertOk();

    $raw = DB::table('settings')
        ->where('group', 'third_party')
        ->where('name', 'openai_api_key')
        ->value('payload');

    expect($raw)->not->toContain('sk-super-secret-123');
    expect(app(ThirdPartySettings::class)->openai_api_key)->toBe('sk-super-secret-123');
});

it('does not overwrite an existing secret when omitted or masked', function (): void {
    [, $token] = settingsWriteToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'openai_api_key' => 'sk-keep-me',
    ])->assertOk();

    // إرسال القناع لا يستبدل السر
    $this->withToken($token)->putJson('/api/v1/admin/settings/third_party', [
        'openai_api_key' => '********',
        'openai_model' => 'gpt-4o',
    ])->assertOk();

    expect(app(ThirdPartySettings::class)->openai_api_key)->toBe('sk-keep-me');
    expect(app(ThirdPartySettings::class)->openai_model)->toBe('gpt-4o');
});

it('masks secrets in the response', function (): void {
    [, $token] = settingsWriteToken();

    $this->withToken($token)->putJson('/api/v1/admin/settings/cdn', [
        'cdn_api_token' => 'cf-token-xyz',
    ])->assertOk()
        ->assertJsonPath('data.api_token', '********')
        ->assertJsonPath('data.api_token_configured', true);
});

// ─── Branding upload ───────────────────────────────────────────────────

it('uploads branding files', function (): void {
    Storage::fake('public');
    [, $token] = settingsWriteToken();

    $response = $this->withToken($token)->postJson('/api/v1/admin/settings/general/branding', [
        'logo_light' => UploadedFile::fake()->image('logo-light.png'),
        'favicon' => UploadedFile::fake()->create('favicon.png', 10, 'image/png'),
    ]);

    $response->assertOk();
    $path = $response->json('data.site.logo_light');
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

// ─── Firebase upload ───────────────────────────────────────────────────

it('uploads valid firebase credentials and extracts project_id', function (): void {
    [, $token] = settingsWriteToken();

    $json = json_encode(['type' => 'service_account', 'project_id' => 'alpha-fb-123']);
    $file = UploadedFile::fake()->createWithContent('sa.json', $json);

    $this->withToken($token)
        ->post('/api/v1/admin/settings/third_party/firebase-credentials', [
            'service_account' => $file,
        ], ['Accept' => 'application/json'])
        ->assertOk();

    expect(app(ThirdPartySettings::class)->firebase_project_id)->toBe('alpha-fb-123');

    File::delete(storage_path('app/private/firebase/service-account.json'));
});

it('rejects invalid firebase json', function (): void {
    [, $token] = settingsWriteToken();

    $file = UploadedFile::fake()->createWithContent('bad.json', '{"no":"project"}');

    $this->withToken($token)
        ->post('/api/v1/admin/settings/third_party/firebase-credentials', [
            'service_account' => $file,
        ], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

// ─── Mail connection test ──────────────────────────────────────────────

it('runs the mail connection test safely', function (): void {
    Mail::fake();
    [, $token] = settingsWriteToken();

    // مطلوب: بريد مُرسِل مضبوط (الاختبار يفشل بدونه — سلوك صحيح)
    app(GeneralSettings::class)->fill(['mail_from_email' => 'noreply@alpha.test'])->save();

    $this->withToken($token)->postJson('/api/v1/admin/settings/mail/test', [
        'to' => 'qa@alpha.test',
    ])->assertOk();
});

// ─── Cloudflare connection test ────────────────────────────────────────

it('passes the cloudflare connection test on valid token', function (): void {
    [, $token] = settingsWriteToken();
    app(CdnSettings::class)->fill(['cdn_api_token' => 'valid'])->save();

    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $this->withToken($token)->postJson('/api/v1/admin/settings/cdn/test')->assertOk();
});

it('fails the cloudflare test on invalid token', function (): void {
    [, $token] = settingsWriteToken();
    app(CdnSettings::class)->fill(['cdn_api_token' => 'bad'])->save();

    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => false], 401),
    ]);

    $this->withToken($token)->postJson('/api/v1/admin/settings/cdn/test')->assertStatus(422);
});

it('fails the cloudflare test when token missing', function (): void {
    [, $token] = settingsWriteToken();

    $this->withToken($token)->postJson('/api/v1/admin/settings/cdn/test')->assertStatus(422);
});

// ─── Security ──────────────────────────────────────────────────────────

it('denies updates without a token', function (): void {
    $this->putJson('/api/v1/admin/settings/general', ['site_name' => 'x'])
        ->assertStatus(401);
});

it('denies updates with a public user-ability token', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $userToken = $admin->createToken('public-token', ['user'])->plainTextToken;

    $this->withToken($userToken)->putJson('/api/v1/admin/settings/general', ['site_name' => 'x'])
        ->assertStatus(403);
});

it('denies updates for an admin lacking settings.edit', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('reviewer');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->putJson('/api/v1/admin/settings/general', ['site_name' => 'x'])
        ->assertStatus(403);
});

it('denies updates for an inactive admin', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;
    $admin->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)->putJson('/api/v1/admin/settings/general', ['site_name' => 'x'])
        ->assertStatus(403);
});
