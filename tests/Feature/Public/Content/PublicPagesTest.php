<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publishedPage(array $attrs = []): Page
{
    return Page::create(array_merge([
        'title' => 'صفحة منشورة',
        'locale' => 'ar',
        'slug' => 'pub-'.uniqid(),
        'status' => 'published',
        'content' => '<p>محتوى</p>',
        'published_at' => now()->subMinute(),
        'show_in_footer' => true,
    ], $attrs));
}

it('shows a published page by slug', function (): void {
    publishedPage(['slug' => 'about-us', 'title' => 'من نحن']);

    $res = $this->getJson('/api/v1/ar/pages/about-us')->assertOk();
    assertSuccessContract($res);
    expect($res->json('data.slug'))->toBe('about-us');
    expect($res->json('data.title'))->toBe('من نحن');
    expect($res->json('data.canonical_path'))->toBe('/ar/pages/about-us');
});

it('returns 404 for an unknown page slug', function (): void {
    $this->getJson('/api/v1/ar/pages/does-not-exist')->assertStatus(404);
});

it('does not expose a draft page publicly', function (): void {
    publishedPage(['slug' => 'draft-page', 'status' => 'draft', 'published_at' => null]);

    $this->getJson('/api/v1/ar/pages/draft-page')->assertStatus(404);
});

it('lists published pages filtered by footer placement', function (): void {
    publishedPage(['slug' => 'footer-one', 'show_in_footer' => true, 'sort_order' => 1]);
    publishedPage(['slug' => 'header-only', 'show_in_footer' => false, 'show_in_header' => true]);

    $res = $this->getJson('/api/v1/ar/pages?placement=footer')->assertOk();
    $slugs = collect($res->json('data'))->pluck('slug')->all();

    expect($slugs)->toContain('footer-one');
    expect($slugs)->not->toContain('header-only');
});
