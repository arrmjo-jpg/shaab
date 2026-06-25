<?php

declare(strict_types=1);

use App\Models\EngagementCounter;
use App\Models\Reel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makePublicReel(array $attrs = []): Reel
{
    return Reel::create(array_merge([
        'title' => 'ريل '.uniqid(),
        'locale' => 'ar',
        'status' => 'published',
        'published_at' => now()->subMinute(),
    ], $attrs));
}

function counterFor(Reel $reel, array $metrics): void
{
    EngagementCounter::create(array_merge([
        'engageable_type' => (new Reel)->getMorphClass(),
        'engageable_id' => $reel->id,
        'views' => 0,
        'likes' => 0,
        'dislikes' => 0,
        'favorites' => 0,
    ], $metrics));
}

// ─── Visibility ─────────────────────────────────────────────────────────────

it('lists only published reels for the locale', function (): void {
    makePublicReel(['title' => 'منشور']);
    makePublicReel(['title' => 'مسودة', 'status' => 'draft', 'published_at' => null]);
    makePublicReel(['title' => 'مؤرشف', 'status' => 'archived']);
    makePublicReel(['title' => 'إنجليزي', 'locale' => 'en']);

    $res = $this->getJson('/api/v1/ar/reels')->assertOk();
    $titles = collect($res->json('data'))->pluck('title');

    expect($titles)->toContain('منشور');
    expect($titles)->not->toContain('مسودة');
    expect($titles)->not->toContain('مؤرشف');
    expect($titles)->not->toContain('إنجليزي');
});

it('shows a published reel by slug and exposes share/seo/media + metrics', function (): void {
    $reel = makePublicReel(['title' => 'مقطع', 'slug' => 'maqtaa']);

    $res = $this->getJson('/api/v1/ar/reels/maqtaa')->assertOk();

    expect($res->json('data.slug'))->toBe('maqtaa');
    expect($res->json('data.canonical_path'))->toBe("/ar/reels/{$reel->id}-maqtaa");
    expect($res->json('data'))->toHaveKeys(['seo', 'metrics', 'media', 'share_image']);
    expect($res->json('data.metrics'))->toHaveKeys(['views', 'likes', 'dislikes', 'favorites']);
});

it('never exposes a draft/unpublished reel on the public detail', function (): void {
    makePublicReel(['title' => 'سرّي', 'slug' => 'secret', 'status' => 'draft', 'published_at' => null]);

    $this->getJson('/api/v1/ar/reels/secret')->assertStatus(404);
});

// ─── Featured (real flag, not ordering) ──────────────────────────────────────

it('featured returns only reels with the is_featured flag', function (): void {
    makePublicReel(['title' => 'مميز', 'is_featured' => true]);
    makePublicReel(['title' => 'عادي', 'is_featured' => false]);

    $res = $this->getJson('/api/v1/ar/reels/featured')->assertOk();
    $titles = collect($res->json('data'))->pluck('title');

    expect($titles)->toContain('مميز');
    expect($titles)->not->toContain('عادي');
});

// ─── Trending (real weighted engagement ordering, not latest) ────────────────

it('trending orders by weighted engagement, not recency', function (): void {
    // يُنشأ A أولاً ثم B ثم C؛ لو كان الترتيب «الأحدث» لتصدّر C.
    $a = makePublicReel(['title' => 'A']);
    counterFor($a, ['views' => 10]); // score = 10

    $b = makePublicReel(['title' => 'B']);
    counterFor($b, ['likes' => 10, 'favorites' => 10]); // score = 40 + 60 = 100

    $c = makePublicReel(['title' => 'C']);
    counterFor($c, ['views' => 50]); // score = 50

    $res = $this->getJson('/api/v1/ar/reels/trending')->assertOk();
    $titles = collect($res->json('data'))->pluck('title')->all();

    // ترتيب التفاعل الموزون: B(100) > C(50) > A(10) — وليس الأحدث C,B,A.
    expect($titles)->toBe(['B', 'C', 'A']);
});

it('trending excludes non-published reels', function (): void {
    $draft = makePublicReel(['title' => 'مسودة', 'status' => 'draft', 'published_at' => null]);
    counterFor($draft, ['likes' => 999]);

    $res = $this->getJson('/api/v1/ar/reels/trending')->assertOk();
    expect(collect($res->json('data'))->pluck('title'))->not->toContain('مسودة');
});

// ─── Locale scoping of slug ──────────────────────────────────────────────────

it('resolves slug within the requested locale only', function (): void {
    makePublicReel(['title' => 'AR', 'slug' => 'shared', 'locale' => 'ar']);
    makePublicReel(['title' => 'EN', 'slug' => 'shared', 'locale' => 'en']);

    expect($this->getJson('/api/v1/ar/reels/shared')->json('data.title'))->toBe('AR');
    expect($this->getJson('/api/v1/en/reels/shared')->json('data.title'))->toBe('EN');
});
