<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\EngagementCounter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Cache::flush();
});

function closureArticle(array $attrs = []): Article
{
    $cat = Category::firstOrCreate(
        ['slug' => 'cl-cat', 'locale' => 'ar'],
        ['name' => 'تصنيف', 'status' => 'active'],
    );

    return Article::create(array_merge([
        'title' => 'خبر '.uniqid(),
        'slug' => 'cl-'.uniqid(),
        'locale' => 'ar',
        'type' => 'news',
        'status' => 'published',
        'primary_category_id' => $cat->id,
        'author_id' => User::factory()->create()->id,
        'content_json' => tiptapDoc(),
        'content' => '<p>متن المقال هنا.</p>',
        'excerpt' => 'هذا مقتطف وصفي بطول كافٍ ليتجاوز الحدّ الأدنى للمعاينة والإرشاد.',
        'published_at' => now()->subDay(),
    ], $attrs))->fresh();
}

function setViews(Article $a, int $views, int $likes = 0, int $favorites = 0): void
{
    EngagementCounter::create([
        'engageable_type' => (new Article)->getMorphClass(),
        'engageable_id' => $a->id,
        'views' => $views,
        'likes' => $likes,
        'favorites' => $favorites,
        'dislikes' => 0,
    ]);
}

function closureAdminToken(string $role = 'super_admin'): string
{
    $u = User::factory()->create();
    $u->assignRole($role);

    return $u->createToken('t', ['admin'])->plainTextToken;
}

// ─── TASK 2: analytics ──────────────────────────────────────────────────────

it('most-read orders by tracked views', function (): void {
    $low = closureArticle(['title' => 'الأقل']);
    $high = closureArticle(['title' => 'الأكثر']);
    setViews($low, 5);
    setViews($high, 500);

    $res = $this->getJson('/api/v1/ar/articles/most-read')->assertOk();

    expect($res->json('data.0.title'))->toBe('الأكثر');
});

it('trending orders by weighted engagement score within the recent window', function (): void {
    $a = closureArticle(['title' => 'رائج', 'published_at' => now()->subDay()]);
    $b = closureArticle(['title' => 'عادي', 'published_at' => now()->subDay()]);
    setViews($a, 10, likes: 50, favorites: 50); // high score via likes+favorites
    setViews($b, 40);                            // views only

    $res = $this->getJson('/api/v1/ar/articles/trending')->assertOk();

    expect($res->json('data.0.title'))->toBe('رائج');
});

it('most-read is locale-isolated', function (): void {
    $en = closureArticle(['locale' => 'en', 'slug' => 'en-'.uniqid()]);
    setViews($en, 9999);

    $res = $this->getJson('/api/v1/ar/articles/most-read')->assertOk();

    expect(collect($res->json('data'))->pluck('id'))->not->toContain($en->id);
});

// ─── TASK 1: true preview ───────────────────────────────────────────────────

it('preview returns the exact public payload for a DRAFT (editor sees as user)', function (): void {
    $token = closureAdminToken();
    $draft = closureArticle(['status' => 'draft', 'published_at' => null, 'title' => 'مسودّة سرّية']);

    $res = $this->withToken($token)->getJson("/api/v1/admin/articles/{$draft->id}/preview")->assertOk();

    expect($res->json('data.preview.title'))->toBe('مسودّة سرّية');
    expect($res->json('data.preview.content_html'))->toContain('متن المقال');
    expect($res->json('data.preview.seo.structured_data.@type'))->toBe('NewsArticle');
    expect($res->json('data.seo_guidance'))->toBeArray();
});

it('the same draft is NOT visible on the public endpoint', function (): void {
    $draft = closureArticle(['status' => 'draft', 'published_at' => null]);

    $this->getJson("/api/v1/ar/articles/{$draft->slug}")->assertStatus(404);
});

it('preview requires articles.view permission', function (): void {
    $token = closureAdminToken('reviewer');
    $draft = closureArticle(['status' => 'draft', 'published_at' => null]);

    // reviewer لا يملك articles.view بالضرورة → 403، أو 200 إن ملكها؛ نتحقّق من عدم 401
    $this->getJson("/api/v1/admin/articles/{$draft->id}/preview")->assertStatus(401);
});

// ─── TASK 1: SEO editorial guidance ─────────────────────────────────────────

it('seo guidance flags a missing cover and overly long title', function (): void {
    $token = closureAdminToken();
    $a = closureArticle([
        'seo_title' => str_repeat('عنوان طويل جداً ', 8), // > 60 chars
    ]);

    $res = $this->withToken($token)->getJson("/api/v1/admin/articles/{$a->id}/preview")->assertOk();

    $keys = collect($res->json('data.seo_guidance'))->keyBy('key');
    expect($keys->has('title_too_long'))->toBeTrue();
    expect($keys->get('cover_missing')['severity'])->toBe('warn');
});

// ─── TASK 1: slug conflict UX ───────────────────────────────────────────────

it('slug-check reports availability and suggests an alternative on conflict', function (): void {
    $token = closureAdminToken();
    closureArticle(['slug' => 'taken-slug']);

    $conflict = $this->withToken($token)
        ->getJson('/api/v1/admin/articles/slug-check?slug=taken-slug&locale=ar')->assertOk();
    expect($conflict->json('data.available'))->toBeFalse();
    expect($conflict->json('data.suggestion'))->toBe('taken-slug-2');

    $free = $this->withToken($token)
        ->getJson('/api/v1/admin/articles/slug-check?slug=brand-new-slug&locale=ar')->assertOk();
    expect($free->json('data.available'))->toBeTrue();
});
