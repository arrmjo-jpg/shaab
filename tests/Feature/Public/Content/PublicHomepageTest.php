<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Cache::flush();
});

/** زون الصفحة الرئيسية ← علم الخبر المقابل (مصدر الحقيقة بعد إلغاء التنسيبات). */
const HOME_ZONE_FLAGS = [
    'hero' => 'is_featured',
    'breaking' => 'is_breaking',
    'header' => 'is_header',
    'editors_pick' => 'is_editor_pick',
];

function homeCat(array $attrs = []): Category
{
    return Category::create(array_merge([
        'name' => 'تصنيف '.uniqid(),
        'locale' => 'ar',
        'status' => 'active',
    ], $attrs));
}

function homeArticle(Category $primary, array $attrs = []): Article
{
    return Article::create(array_merge([
        'title' => 'مقال '.uniqid(),
        'locale' => $primary->locale,
        'type' => 'news',
        'status' => 'published',
        'primary_category_id' => $primary->id,
        'author_id' => User::factory()->create()->id,
        'content_json' => tiptapDoc(),
        'content' => '<p>محتوى</p>',
        'published_at' => now()->subMinute(),
    ], $attrs))->fresh();
}

it('returns every zone + latest in a single response', function (): void {
    $cat = homeCat();
    foreach (HOME_ZONE_FLAGS as $zone => $flag) {
        homeArticle($cat, ['title' => "zone-{$zone}", $flag => true]);
    }
    homeArticle($cat, ['title' => 'latest-only']);

    $res = $this->getJson('/api/v1/ar/homepage');

    $res->assertOk();
    assertSuccessContract($res);
    foreach (HOME_ZONE_FLAGS as $zone => $flag) {
        expect($res->json("data.{$zone}"))->toBeArray();
        expect($res->json("data.{$zone}.0.title"))->toBe("zone-{$zone}");
    }
    expect($res->json('data.latest'))->toBeArray();
    expect(count($res->json('data.latest')))->toBeGreaterThanOrEqual(1);
});

it('returns empty zone buckets when no article carries the flag', function (): void {
    homeArticle(homeCat()); // بلا أي علم

    $res = $this->getJson('/api/v1/ar/homepage');

    $res->assertOk();
    foreach (array_keys(HOME_ZONE_FLAGS) as $zone) {
        expect($res->json("data.{$zone}"))->toBe([]);
    }
    expect(count($res->json('data.latest')))->toBeGreaterThanOrEqual(1);
});

it('isolates the homepage by locale', function (): void {
    $arCat = homeCat();
    $enCat = homeCat(['name' => 'en-cat', 'locale' => 'en']);
    homeArticle($arCat, ['title' => 'AR-hero', 'is_featured' => true]);
    homeArticle($enCat, ['title' => 'EN-hero', 'is_featured' => true]);

    expect($this->getJson('/api/v1/ar/homepage')->json('data.hero.0.title'))->toBe('AR-hero');
    expect($this->getJson('/api/v1/en/homepage')->json('data.hero.0.title'))->toBe('EN-hero');
});

it('drops unpublished articles from each zone bucket', function (): void {
    $cat = homeCat();
    homeArticle($cat, ['title' => 'منشور', 'is_featured' => true]);
    homeArticle($cat, ['title' => 'مسودّة', 'is_featured' => true, 'status' => 'draft', 'published_at' => null]);

    $res = $this->getJson('/api/v1/ar/homepage');

    $res->assertOk();
    expect($res->json('data.hero'))->toHaveCount(1);
    expect($res->json('data.hero.0.title'))->toBe('منشور');
});

it('orders each zone by pinned first then newest', function (): void {
    $cat = homeCat();
    homeArticle($cat, ['title' => 'مميّز أحدث', 'is_featured' => true, 'published_at' => now()->subHour()]);
    homeArticle($cat, ['title' => 'مميّز مثبّت', 'is_featured' => true, 'is_pinned' => true, 'published_at' => now()->subWeek()]);

    $res = $this->getJson('/api/v1/ar/homepage');

    $res->assertOk();
    expect($res->json('data.hero.0.title'))->toBe('مميّز مثبّت');
    expect($res->json('data.hero.1.title'))->toBe('مميّز أحدث');
});

it('attaches CDN-aware Cache-Control on the homepage', function (): void {
    $res = $this->getJson('/api/v1/ar/homepage');
    $res->assertOk();
    expect($res->headers->get('Cache-Control'))->toContain('public');
    expect($res->headers->get('Cache-Control'))->toContain('s-maxage=');
    expect((string) $res->headers->get('Vary'))->not->toContain('Accept-Language');
});

it('flushes the homepage cache when an admin transitions an article to published', function (): void {
    $cat = homeCat();
    $draft = homeArticle($cat, ['title' => 'سيُنشر', 'status' => 'draft', 'published_at' => null]);

    $first = $this->getJson('/api/v1/ar/homepage');
    $first->assertOk();
    $initialLatest = collect($first->json('data.latest'))->pluck('title')->all();
    expect($initialLatest)->not->toContain('سيُنشر');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/admin/articles/{$draft->id}/status", [
        'status' => 'published',
    ])->assertOk();

    $next = $this->getJson('/api/v1/ar/homepage');
    $nextLatest = collect($next->json('data.latest'))->pluck('title')->all();
    expect($nextLatest)->toContain('سيُنشر');
});
