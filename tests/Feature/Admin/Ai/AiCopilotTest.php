<?php

declare(strict_types=1);

use App\Models\User;
use App\Settings\ThirdPartySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    // إعدادات اللوحة هي مصدر الحقيقة — لا .env.
    app(ThirdPartySettings::class)->fill([
        'ai_enabled' => true,
        'ai_provider' => 'openai',
        'openai_api_key' => 'sk-test',
        'openai_base_url' => 'https://api.openai.com/v1',
        'openai_model' => 'gpt-4o-mini',
    ])->save();
});

function aiToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin-token', ['admin'])->plainTextToken;
}

function fakeOpenAi(array $content): void
{
    Http::fake([
        '*openai.com*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]]],
        ], 200),
    ]);
}

function setAi(array $overrides): void
{
    app(ThirdPartySettings::class)->fill($overrides)->save();
}

// ─── Provider wiring (reads ThirdPartySettings) ────────────────────────────

it('routes headlines through the configured OpenAI provider', function (): void {
    fakeOpenAi([
        'news' => ['ع1', 'ع2', 'ع3', 'ع4', 'ع5'],
        'editorial' => ['ت1', 'ت2', 'ت3'],
        'seo' => ['س1', 'س2', 'س3'],
    ]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/headlines', [
        'title' => 'عنوان أولي', 'body' => 'متن الخبر للسياق التحريري الكافي.',
        'type' => 'news',
    ])->assertOk();

    expect($res->json('data.news'))->toHaveCount(5);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'openai.com'));
});

it('cleans numbering, quotes and markdown from suggestions', function (): void {
    fakeOpenAi([
        'news' => ['1. عنوان أول', '"عنوان «مقتبس»"', '**عنوان غامق**'],
        'editorial' => ['- عنوان بنقطة'],
        'seo' => ['2) عنوان مرقّم'],
    ]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/headlines', [
        'title' => 'عنوان', 'body' => 'متن كافٍ للسياق التحريري.',
    ])->assertOk();

    expect($res->json('data.news'))->toContain('عنوان أول');
    expect($res->json('data.news'))->toContain('عنوان غامق');
    expect($res->json('data.editorial.0'))->toBe('عنوان بنقطة');
    expect($res->json('data.seo.0'))->toBe('عنوان مرقّم');
});

it('routes through Gemini when the panel selects it', function (): void {
    setAi(['ai_provider' => 'gemini', 'gemini_api_key' => 'g-test', 'gemini_model' => 'gemini-1.5-flash']);

    Http::fake([
        '*generativelanguage*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['excerpt' => 'ملخّص جيميناي.'])]]]]],
        ], 200),
    ]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/excerpt', [
        'body' => 'متن طويل بما يكفي لتوليد ملخّص جيد ومفيد للقارئ.',
    ])->assertOk();

    expect($res->json('data.excerpt'))->toBe('ملخّص جيميناي.');
    expect($res->json('data.source'))->toBe('ai');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage'));
});

// ─── AI features ──────────────────────────────────────────────────────────

it('generates an excerpt via AI', function (): void {
    fakeOpenAi(['excerpt' => 'مقتطف تحريري موجز.']);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/excerpt', [
        'title' => 'عنوان', 'body' => 'متن طويل بما يكفي لتوليد ملخّص جيد ومفيد.',
    ])->assertOk();

    expect($res->json('data.excerpt'))->toBe('مقتطف تحريري موجز.');
    expect($res->json('data.source'))->toBe('ai');
});

it('rewrites selected text in a given mode', function (): void {
    fakeOpenAi(['rewrite' => 'صياغة محسّنة.']);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص أصلي', 'mode' => 'journalistic', 'locale' => 'ar',
    ])->assertOk();

    expect($res->json('data.rewrite'))->toBe('صياغة محسّنة.');
});

it('rejects an unknown rewrite mode', function (): void {
    $this->withToken(aiToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص', 'mode' => 'poetic',
    ])->assertStatus(422)->assertJsonValidationErrors('mode');
});

it('suggests grouped smart tags via AI', function (): void {
    fakeOpenAi([
        'people' => ['شخص أ'], 'locations' => ['مدينة'],
        'organizations' => ['منظمة'], 'topics' => ['اقتصاد'],
    ]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/tags', [
        'body' => 'متن يحتوي على أشخاص وأماكن ومنظمات ومواضيع.',
    ])->assertOk();

    expect($res->json('data.topics'))->toContain('اقتصاد');
    expect($res->json('data.source'))->toBe('ai');
});

it('analyzes content quality via AI', function (): void {
    fakeOpenAi([
        'score' => 72, 'readability' => 'سهل القراءة نسبياً.',
        'issues' => ['تكرار كلمة «الحكومة».'], 'suggestions' => ['نوّع المفردات.'],
    ]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/analyze', [
        'title' => 'عنوان', 'body' => 'متن طويل بما يكفي لتحليل جودته التحريرية بدقّة.',
    ])->assertOk();

    expect($res->json('data.score'))->toBe(72);
    expect($res->json('data.issues'))->toContain('تكرار كلمة «الحكومة».');
    expect($res->json('data.suggestions'))->toBeArray();
});

it('accepts the professional and seo rewrite modes', function (): void {
    fakeOpenAi(['rewrite' => 'صياغة احترافية.']);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص أصلي', 'mode' => 'professional', 'locale' => 'ar',
    ])->assertOk();
    expect($res->json('data.rewrite'))->toBe('صياغة احترافية.');

    $this->withToken(aiToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص أصلي', 'mode' => 'seo', 'locale' => 'ar',
    ])->assertOk();
});

it('analyzes SEO via AI and bounds the score', function (): void {
    fakeOpenAi([
        'score' => 250, 'title_feedback' => 'مناسب.', 'description_feedback' => 'قصير.',
        'missing_keywords' => ['كلمة'], 'suggestions' => ['حسّن العنوان.'],
    ]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/seo', [
        'title' => 'عنوان', 'excerpt' => 'وصف',
    ])->assertOk();

    expect($res->json('data.score'))->toBe(100);
    expect($res->json('data.source'))->toBe('ai');
});

// ─── Heuristic fallback (no AI) ─────────────────────────────────────────────

it('falls back to an auto excerpt when AI is disabled', function (): void {
    setAi(['ai_enabled' => false]);
    Http::fake(); // لا نداء خارجي متوقّع

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/excerpt', [
        'title' => 'عنوان الخبر',
        'body' => 'الجملة الأولى من المتن. الجملة الثانية تضيف تفاصيل. والثالثة إضافية.',
    ])->assertOk();

    expect($res->json('data.source'))->toBe('auto');
    expect($res->json('data.excerpt'))->toContain('الجملة الأولى');
    Http::assertNothingSent();
});

it('falls back to an auto excerpt when the provider key is missing', function (): void {
    setAi(['openai_api_key' => '']);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/excerpt', [
        'body' => 'متن المقال يتحوّل تلقائياً إلى ملخّص قصير عند غياب الذكاء الاصطناعي.',
    ])->assertOk();

    expect($res->json('data.source'))->toBe('auto');
    expect($res->json('data.excerpt'))->not->toBe('');
});

it('falls back to a heuristic SEO analysis when AI is disabled', function (): void {
    setAi(['ai_enabled' => false]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/seo', [
        'title' => 'عنوان قصير',
        'excerpt' => 'وصف موجز للمقال.',
        'slug' => 'news-slug',
        'tags' => ['سياسة'],
        'body' => 'نصّ المقال الكامل لاشتقاق الكلمات المفتاحية المقترحة.',
    ])->assertOk();

    expect($res->json('data.source'))->toBe('auto');
    expect($res->json('data.score'))->toBeGreaterThanOrEqual(0);
    expect($res->json('data.title_feedback'))->toBeString();
});

it('falls back to an auto excerpt even when the AI call errors', function (): void {
    Http::fake(['*openai.com*' => Http::response('', 500)]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/excerpt', [
        'body' => 'متن يضمن وجود ملخّص حتى عند تعطّل المزوّد الخارجي.',
    ])->assertOk();

    expect($res->json('data.source'))->toBe('auto');
});

// ─── AI-only features still gate gracefully ─────────────────────────────────

it('returns 503 for headlines when AI is not configured', function (): void {
    setAi(['openai_api_key' => '']);

    $this->withToken(aiToken())->postJson('/api/v1/admin/ai/headlines', [
        'title' => 'عنوان',
    ])->assertStatus(503);
});

it('returns 503 for content analysis when AI is disabled', function (): void {
    setAi(['ai_enabled' => false]);
    Http::fake();

    $this->withToken(aiToken())->postJson('/api/v1/admin/ai/analyze', [
        'body' => 'متن كافٍ للتحليل التحريري.',
    ])->assertStatus(503);
    Http::assertNothingSent();
});

it('falls back to deterministic tags when AI is disabled', function (): void {
    setAi(['ai_enabled' => false]);
    Http::fake();

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/tags', [
        'title' => 'ارتفاع أسعار الوقود في الأسواق',
        'body' => 'سجّلت أسعار الوقود ارتفاعاً ملحوظاً في الأسواق المحلية خلال الأسبوع الجاري، '
            .'مع توقّعات بمزيد من الارتفاع في الوقود والمحروقات.',
    ])->assertOk();

    expect($res->json('data.source'))->toBe('auto');
    expect($res->json('data.topics'))->toBeArray()->not->toBeEmpty();
    // كلمة وظيفية شائعة يجب ألّا تظهر كوسم.
    expect($res->json('data.topics'))->not->toContain('في');
    Http::assertNothingSent();
});

it('returns 503 for rewrite on a provider transport error', function (): void {
    Http::fake(['*openai.com*' => Http::response('', 500)]);

    $this->withToken(aiToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص', 'mode' => 'concise',
    ])->assertStatus(503);
});

// ─── Provider failover ──────────────────────────────────────────────────────

it('fails over to the secondary provider when the primary fails', function (): void {
    // الأساسي gemini يفشل (429 quota) → الاحتياطي openai ينجح.
    setAi(['ai_provider' => 'gemini', 'gemini_api_key' => 'g-test', 'openai_api_key' => 'sk-test']);

    Http::fake([
        '*generativelanguage*' => Http::response(['error' => ['code' => 429]], 429),
        '*openai.com*' => Http::response([
            'choices' => [['message' => [
                'content' => json_encode(['rewrite' => 'صياغة احتياطية.'], JSON_UNESCAPED_UNICODE),
            ]]],
        ], 200),
    ]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص أصلي', 'mode' => 'journalistic', 'locale' => 'ar',
    ])->assertOk();

    expect($res->json('data.rewrite'))->toBe('صياغة احتياطية.');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage')); // جُرّب الأساسي
    Http::assertSent(fn ($r) => str_contains($r->url(), 'openai.com'));         // ثم الاحتياطي
});

it('applies failover to headlines and content analysis too', function (): void {
    setAi(['ai_provider' => 'gemini', 'gemini_api_key' => 'g-test', 'openai_api_key' => 'sk-test']);

    Http::fake([
        '*generativelanguage*' => Http::response('', 404),
        '*openai.com*' => Http::response([
            'choices' => [['message' => [
                'content' => json_encode([
                    'news' => ['ع1', 'ع2', 'ع3', 'ع4', 'ع5'], 'editorial' => ['ت1'], 'seo' => ['س1'],
                    'score' => 80, 'readability' => 'جيّد.', 'issues' => [], 'suggestions' => [],
                ], JSON_UNESCAPED_UNICODE),
            ]]],
        ], 200),
    ]);

    $this->withToken(aiToken())->postJson('/api/v1/admin/ai/headlines', [
        'title' => 'عنوان', 'body' => 'متن كافٍ للسياق التحريري.',
    ])->assertOk()->assertJsonPath('data.news', ['ع1', 'ع2', 'ع3', 'ع4', 'ع5']);

    $this->withToken(aiToken())->postJson('/api/v1/admin/ai/analyze', [
        'body' => 'متن طويل بما يكفي لتحليل جودته التحريرية.',
    ])->assertOk()->assertJsonPath('data.score', 80);
});

it('fails user-facing only when all providers fail', function (): void {
    setAi(['ai_provider' => 'gemini', 'gemini_api_key' => 'g-test', 'openai_api_key' => 'sk-test']);

    Http::fake([
        '*generativelanguage*' => Http::response('', 500),
        '*openai.com*' => Http::response('', 503),
    ]);

    $this->withToken(aiToken())->postJson('/api/v1/admin/ai/rewrite', [
        'text' => 'نص', 'mode' => 'concise',
    ])->assertStatus(503);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'openai.com'));
});

it('still falls back to the heuristic excerpt when all providers fail', function (): void {
    // التجاوز يستنفد المزوّدين، ثم البديل الحتمي يضمن نتيجة (hybrid سليم).
    setAi(['ai_provider' => 'gemini', 'gemini_api_key' => 'g-test', 'openai_api_key' => 'sk-test']);

    Http::fake([
        '*generativelanguage*' => Http::response('', 500),
        '*openai.com*' => Http::response('', 500),
    ]);

    $res = $this->withToken(aiToken())->postJson('/api/v1/admin/ai/excerpt', [
        'body' => 'متن يضمن وجود ملخّص حتى عند فشل جميع المزوّدين الخارجيين.',
    ])->assertOk();

    expect($res->json('data.source'))->toBe('auto');
});

// ─── RBAC ───────────────────────────────────────────────────────────────────

it('forbids users without ai.use permission', function (): void {
    $other = User::factory()->create();
    $other->assignRole('journalist'); // لا يملك ai.use
    $token = $other->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/ai/excerpt', [
        'body' => 'متن كافٍ للسياق التحريري.',
    ])->assertForbidden();
});
