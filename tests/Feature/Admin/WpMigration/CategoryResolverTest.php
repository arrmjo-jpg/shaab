<?php

declare(strict_types=1);

use App\Enums\ArticleType;
use App\Enums\ConflictPolicy;
use App\Support\WpMigration\MigrationCategoryResolver;

/** ttid => type/target/weight/term_id (100,101 = أخبار؛ 200 = مقالات). */
function ttidMap(): array
{
    return [
        100 => ['type' => 'news', 'target' => 11, 'weight' => 500, 'term_id' => 10],
        101 => ['type' => 'news', 'target' => 12, 'weight' => 200, 'term_id' => 20],
        200 => ['type' => 'articles', 'target' => 21, 'weight' => 50, 'term_id' => 30],
    ];
}

it('resolves a single news category', function (): void {
    $r = MigrationCategoryResolver::resolve([100], ttidMap(), ConflictPolicy::PreferNews);

    expect($r)->not->toBeNull();
    expect($r->type)->toBe(ArticleType::News);
    expect($r->primary)->toBe(11);
    expect($r->secondary)->toBe([]);
});

it('keeps all unique mapped categories (multi-category, same type — no cap)', function (): void {
    $r = MigrationCategoryResolver::resolve([100, 101], ttidMap(), ConflictPolicy::PreferNews);

    expect($r->type)->toBe(ArticleType::News);
    expect($r->primary)->toBe(11);        // أعلى وزناً
    expect($r->secondary)->toBe([12]);    // مُحتفَظ بها (لا اقتطاع)
});

it('resolves a conflict with prefer_news (retain only news, discard articles)', function (): void {
    $r = MigrationCategoryResolver::resolve([100, 200], ttidMap(), ConflictPolicy::PreferNews);

    expect($r->type)->toBe(ArticleType::News);
    expect($r->primary)->toBe(11);
    expect($r->secondary)->toBe([]); // الهدف المقالي 21 مُهمَل
});

it('resolves a conflict with prefer_articles (retain only articles, discard news)', function (): void {
    $r = MigrationCategoryResolver::resolve([100, 200], ttidMap(), ConflictPolicy::PreferArticles);

    expect($r->type)->toBe(ArticleType::Opinion);
    expect($r->primary)->toBe(21);
    expect($r->secondary)->toBe([]); // الأهداف الإخبارية مُهمَلة
});

it('excludes conflicted posts under the exclude policy', function (): void {
    expect(MigrationCategoryResolver::resolve([100, 200], ttidMap(), ConflictPolicy::Exclude))->toBeNull();
});

it('uses the Yoast primary category when it is among the kept set', function (): void {
    $r = MigrationCategoryResolver::resolve([100, 101], ttidMap(), ConflictPolicy::PreferNews, 101);

    expect($r->primary)->toBe(12);     // هدف tt 101
    expect($r->secondary)->toBe([11]);
});

it('falls back to highest-weight then lowest term_id for primary', function (): void {
    $r = MigrationCategoryResolver::resolve([101, 100], ttidMap(), ConflictPolicy::PreferNews);

    expect($r->primary)->toBe(11); // tt 100 (weight 500) يفوز رغم ترتيب الإدخال
});

it('ignores a Yoast primary that conflict policy discarded', function (): void {
    // تعارض، prefer_news، وyoast الرئيسي = 200 (مقالات، مُهمَل) → fallback إخباري.
    $r = MigrationCategoryResolver::resolve([100, 200], ttidMap(), ConflictPolicy::PreferNews, 200);

    expect($r->type)->toBe(ArticleType::News);
    expect($r->primary)->toBe(11);
});

it('deduplicates when several source categories map to the same target', function (): void {
    $map = [
        100 => ['type' => 'news', 'target' => 11, 'weight' => 500, 'term_id' => 10],
        101 => ['type' => 'news', 'target' => 11, 'weight' => 200, 'term_id' => 20],
    ];
    $r = MigrationCategoryResolver::resolve([100, 101], $map, ConflictPolicy::PreferNews);

    expect($r->primary)->toBe(11);
    expect($r->secondary)->toBe([]); // لا تكرار للهدف 11
});

it('returns null when the post is in no selected category', function (): void {
    expect(MigrationCategoryResolver::resolve([999], ttidMap(), ConflictPolicy::PreferNews))->toBeNull();
});
