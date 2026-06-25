<?php

declare(strict_types=1);

use App\Actions\Admin\Content\DeleteArticleAction;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Cache\CachedRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Cache::flush();
});

function csArticle(string $slug, string $locale, string $catSlug): Article
{
    $cat = Category::firstOrCreate(
        ['slug' => $catSlug, 'locale' => $locale],
        ['name' => 'c-'.$catSlug, 'status' => 'active'],
    );

    return Article::create([
        'title' => 't-'.$slug,
        'slug' => $slug,
        'locale' => $locale,
        'type' => 'news',
        'status' => 'published',
        'primary_category_id' => $cat->id,
        'author_id' => User::factory()->create()->id,
        'content_json' => tiptapDoc(),
        'content' => '<p>x</p>',
        'excerpt' => 'x',
        'published_at' => now()->subDay(),
    ])->fresh();
}

// ─── TASK 1: single-flight cache (stampede protection) ──────────────────────

it('caches the computed value and does not recompute on a hit', function (): void {
    $calls = 0;
    $cb = function () use (&$calls): string {
        $calls++;

        return 'value';
    };

    $a = CachedRead::remember(['articles'], 'cs:k1', 300, $cb);
    $b = CachedRead::remember(['articles'], 'cs:k1', 300, $cb);

    expect($a)->toBe('value');
    expect($b)->toBe('value');
    expect($calls)->toBe(1); // single computation — no stampede re-run
});

it('caches a null result (no recompute storm on 404/redirect paths)', function (): void {
    $calls = 0;
    $cb = function () use (&$calls): ?string {
        $calls++;

        return null;
    };

    $first = CachedRead::remember(['articles'], 'cs:knull', 300, $cb);
    $second = CachedRead::remember(['articles'], 'cs:knull', 300, $cb);

    expect($first)->toBeNull();
    expect($second)->toBeNull();
    expect($calls)->toBe(1); // cached null distinguished from a miss
});

// ─── TASK 2: granular invalidation (isolation) ──────────────────────────────

it('granular invalidation flushes only the impacted article/feed/category tags', function (): void {
    $a = csArticle('a-slug', 'ar', 'cat-a');
    csArticle('b-slug', 'ar', 'cat-b');

    // Prime cache entries the way the real read paths tag them.
    Cache::tags(ArticleCacheTags::detailTags('ar', 'a-slug'))->put('k_detail_a', ['v' => 1], 300);
    Cache::tags(ArticleCacheTags::detailTags('ar', 'b-slug'))->put('k_detail_b', ['v' => 1], 300);
    Cache::tags(ArticleCacheTags::feedTags('ar'))->put('k_feed_ar', ['v' => 1], 300);
    Cache::tags(ArticleCacheTags::feedTags('en'))->put('k_feed_en', ['v' => 1], 300);
    Cache::tags(ArticleCacheTags::categoryTags('ar', 'cat-a'))->put('k_cat_a', ['v' => 1], 300);
    Cache::tags(ArticleCacheTags::categoryTags('ar', 'cat-b'))->put('k_cat_b', ['v' => 1], 300);

    (new DeleteArticleAction)->handle($a); // writes article A (ar, slug a-slug, cat-a)

    // Impacted → flushed:
    expect(Cache::tags(ArticleCacheTags::detailTags('ar', 'a-slug'))->get('k_detail_a'))->toBeNull();
    expect(Cache::tags(ArticleCacheTags::feedTags('ar'))->get('k_feed_ar'))->toBeNull();
    expect(Cache::tags(ArticleCacheTags::categoryTags('ar', 'cat-a'))->get('k_cat_a'))->toBeNull();

    // Unrelated → intact (the whole point of granularity):
    expect(Cache::tags(ArticleCacheTags::detailTags('ar', 'b-slug'))->get('k_detail_b'))->toBe(['v' => 1]);
    expect(Cache::tags(ArticleCacheTags::feedTags('en'))->get('k_feed_en'))->toBe(['v' => 1]);
    expect(Cache::tags(ArticleCacheTags::categoryTags('ar', 'cat-b'))->get('k_cat_b'))->toBe(['v' => 1]);
});

it('builds slug-change invalidation tags including the old detail tag', function (): void {
    $a = csArticle('new-slug', 'ar', 'cat-a');

    $tags = ArticleCacheTags::invalidationTags($a, 'ar', 'old-slug', ['cat-a']);

    expect($tags)->toContain(ArticleCacheTags::detail('ar', 'new-slug'));
    expect($tags)->toContain(ArticleCacheTags::detail('ar', 'old-slug'));
    expect($tags)->toContain(ArticleCacheTags::feed('ar'));
});

it('builds locale-change invalidation tags including the old feed tag', function (): void {
    $a = csArticle('s', 'en', 'cat-a');

    $tags = ArticleCacheTags::invalidationTags($a, 'ar', 's', ['cat-a']);

    expect($tags)->toContain(ArticleCacheTags::feed('en'));
    expect($tags)->toContain(ArticleCacheTags::feed('ar')); // old locale feed
});
