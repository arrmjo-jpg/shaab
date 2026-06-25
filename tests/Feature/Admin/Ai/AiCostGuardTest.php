<?php

declare(strict_types=1);

use App\Models\AiUsage;
use App\Models\User;
use App\Settings\ThirdPartySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    app(ThirdPartySettings::class)->fill([
        'ai_enabled' => true,
        'ai_provider' => 'openai',
        'openai_api_key' => 'sk-test',
        'openai_base_url' => 'https://api.openai.com/v1',
        'openai_model' => 'gpt-4o-mini',
    ])->save();
});

function guardAdminToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin-token', ['admin'])->plainTextToken;
}

function fakeOpenAiOk(array $content): void
{
    Http::fake([
        '*openai.com*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]]],
        ], 200),
    ]);
}

// ─── Usage recording (queryable) ───────────────────────────────────────────

it('records a queryable usage row for a successful AI call', function (): void {
    fakeOpenAiOk(['excerpt' => 'مقتطف.']);

    $this->withToken(guardAdminToken())->postJson('/api/v1/admin/ai/excerpt', [
        'title' => 'عنوان', 'body' => 'متن طويل بما يكفي لتوليد ملخّص جيد.',
    ])->assertOk();

    $row = AiUsage::query()->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->source)->toBe('ai');
    expect($row->provider)->toBe('openai');
    expect($row->action)->toBe('excerpt');
    expect($row->tokens)->toBeGreaterThan(0);
    expect((float) $row->estimated_cost)->toBeGreaterThan(0.0);
});

it('records an auto fallback as source=auto with zero cost', function (): void {
    app(ThirdPartySettings::class)->fill(['ai_enabled' => false])->save();
    Http::fake();

    $this->withToken(guardAdminToken())->postJson('/api/v1/admin/ai/excerpt', [
        'title' => 'عنوان', 'body' => 'الجملة الأولى. الجملة الثانية. الجملة الثالثة.',
    ])->assertOk();

    $row = AiUsage::query()->latest('id')->first();
    expect($row->source)->toBe('auto');
    expect($row->provider)->toBe('none');
    expect($row->tokens)->toBe(0);
    expect((float) $row->estimated_cost)->toBe(0.0);
});

// ─── Cost guard enforcement ─────────────────────────────────────────────────

it('refuses an AI-only feature with 429 once the daily request cap is reached', function (): void {
    config(['ai.caps.daily_requests' => 2]);
    AiUsage::factory()->count(2)->create(); // مُحاكاة استهلاك اليوم

    fakeOpenAiOk(['rewrite' => 'صياغة.']);

    $this->withToken(guardAdminToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص', 'mode' => 'concise',
    ])->assertStatus(429);

    Http::assertNothingSent(); // لم يُجرَ نداء خارجي بعد التجاوز
});

it('degrades a hybrid feature to the deterministic fallback when capped', function (): void {
    config(['ai.caps.daily_requests' => 1]);
    AiUsage::factory()->create();

    Http::fake();

    $res = $this->withToken(guardAdminToken())->postJson('/api/v1/admin/ai/excerpt', [
        'title' => 'عنوان', 'body' => 'الجملة الأولى. الجملة الثانية تكفي للبديل.',
    ])->assertOk();

    expect($res->json('data.source'))->toBe('auto');
    Http::assertNothingSent();
});

it('enforces the monthly budget cap', function (): void {
    config(['ai.caps.monthly_budget_usd' => 1.0]);
    AiUsage::factory()->create(['estimated_cost' => 5.0]);

    fakeOpenAiOk(['rewrite' => 'صياغة.']);

    $this->withToken(guardAdminToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص', 'mode' => 'concise',
    ])->assertStatus(429);
});

it('enforces the per-user daily cap', function (): void {
    config(['ai.caps.user_daily_requests' => 1]);

    $token = guardAdminToken();
    $userId = User::query()->latest('id')->first()->id;
    AiUsage::factory()->create(['user_id' => $userId]);

    fakeOpenAiOk(['rewrite' => 'صياغة.']);

    $this->withToken($token)->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص', 'mode' => 'concise',
    ])->assertStatus(429);
});

it('does not count auto rows toward the cap', function (): void {
    config(['ai.caps.daily_requests' => 2]);
    AiUsage::factory()->count(5)->create(['source' => 'auto', 'provider' => 'none']);

    fakeOpenAiOk(['rewrite' => 'صياغة.']);

    $this->withToken(guardAdminToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص', 'mode' => 'concise',
    ])->assertOk(); // النداءات الحتمية لا تُحتسب
});

it('allows AI calls when no caps are configured (fail-safe)', function (): void {
    config(['ai.caps' => [
        'daily_requests' => 0, 'monthly_requests' => 0,
        'user_daily_requests' => 0, 'monthly_budget_usd' => 0,
    ]]);
    AiUsage::factory()->count(100)->create();

    fakeOpenAiOk(['rewrite' => 'صياغة.']);

    $this->withToken(guardAdminToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص', 'mode' => 'concise',
    ])->assertOk();
});

// ─── Admin usage visibility ─────────────────────────────────────────────────

it('exposes usage totals, caps and a filtered list to ai.settings holders', function (): void {
    config(['ai.caps.daily_requests' => 50, 'ai.caps.monthly_budget_usd' => 10.0]);
    AiUsage::factory()->count(3)->create(['source' => 'ai', 'provider' => 'openai', 'tokens' => 100, 'estimated_cost' => 0.5]);

    $res = $this->withToken(guardAdminToken())->getJson('/api/v1/admin/ai/usage')->assertOk();

    expect($res->json('meta.totals.month.requests'))->toBeGreaterThanOrEqual(3);
    expect($res->json('meta.caps.daily_requests'))->toBe(50);
    expect($res->json('meta.caps.remaining.monthly_budget_usd'))->not->toBeNull();
    expect($res->json('data'))->toBeArray();
});

it('filters usage by provider', function (): void {
    AiUsage::factory()->create(['provider' => 'openai', 'source' => 'ai']);
    AiUsage::factory()->create(['provider' => 'gemini', 'source' => 'ai']);

    $res = $this->withToken(guardAdminToken())
        ->getJson('/api/v1/admin/ai/usage?filter[provider]=gemini')->assertOk();

    foreach ($res->json('data') as $row) {
        expect($row['provider'])->toBe('gemini');
    }
});

it('forbids the usage endpoint without ai.settings permission', function (): void {
    $u = User::factory()->create();
    $u->assignRole('journalist'); // لا يملك ai.settings
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/ai/usage')->assertForbidden();
});

it('does not leak any sensitive content in usage rows', function (): void {
    AiUsage::factory()->create(['source' => 'ai']);

    $res = $this->withToken(guardAdminToken())->getJson('/api/v1/admin/ai/usage')->assertOk();

    $keys = array_keys($res->json('data.0'));
    expect($keys)->not->toContain('content');
    expect($keys)->not->toContain('prompt');
    expect($keys)->not->toContain('output');
});
