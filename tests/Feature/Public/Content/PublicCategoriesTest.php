<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Cache::flush();
});

// ─── Tree ──────────────────────────────────────────────────────────────

it('returns the active category tree for the locale prefix', function (): void {
    $root = Category::create(['name' => 'رياضة', 'locale' => 'ar', 'status' => 'active']);
    Category::create(['name' => 'كرة قدم', 'locale' => 'ar', 'status' => 'active', 'parent_id' => $root->id]);
    Category::create(['name' => 'مخفي', 'locale' => 'ar', 'status' => 'hidden']);

    $res = $this->getJson('/api/v1/ar/categories');

    $res->assertOk();
    assertSuccessContract($res);
    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.children'))->toHaveCount(1);
});

it('rejects an unsupported locale via the route constraint', function (): void {
    $this->getJson('/api/v1/de/categories')->assertNotFound();
});

it('does not leak admin-only fields in the public tree', function (): void {
    Category::create(['name' => 'سياسة', 'locale' => 'ar', 'status' => 'active', 'scope' => 'news']);

    $res = $this->getJson('/api/v1/ar/categories');

    $row = $res->json('data.0');
    expect(array_keys($row))->not->toContain('scope');
    expect(array_keys($row))->not->toContain('sort_order');
    expect(array_keys($row))->not->toContain('parent_id');
    expect(array_keys($row))->not->toContain('show_in_header');
    expect(array_keys($row))->not->toContain('translation_group');
});

it('attaches CDN-aware Cache-Control on the categories tree', function (): void {
    Category::create(['name' => 'سياسة', 'locale' => 'ar', 'status' => 'active']);

    $res = $this->getJson('/api/v1/ar/categories');

    $res->assertOk();
    expect($res->headers->get('Cache-Control'))->toContain('public');
    expect((string) $res->headers->get('Vary'))->not->toContain('Accept-Language');
});

// ─── Detail ────────────────────────────────────────────────────────────

it('shows a category by slug under the locale prefix', function (): void {
    $cat = Category::create([
        'name' => 'سياسة',
        'locale' => 'ar',
        'status' => 'active',
    ]);

    $res = $this->getJson('/api/v1/ar/categories/'.$cat->slug);

    $res->assertOk();
    assertSuccessContract($res);
    expect($res->json('data.slug'))->toBe($cat->slug);
    expect($res->json('data.name'))->toBe('سياسة');
});

it('returns 404 for an inactive category', function (): void {
    $cat = Category::create([
        'name' => 'مخفي',
        'locale' => 'ar',
        'status' => 'hidden',
    ]);

    $this->getJson('/api/v1/ar/categories/'.$cat->slug)->assertNotFound();
});

it('returns 404 for a non-existent category slug', function (): void {
    $this->getJson('/api/v1/ar/categories/missing')->assertNotFound();
});
