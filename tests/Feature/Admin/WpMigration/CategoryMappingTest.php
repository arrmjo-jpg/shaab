<?php

declare(strict_types=1);

use App\Enums\WpCategoryMode;
use App\Models\Category;
use App\Models\MigrationCategoryMap;
use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function cmRun(): MigrationRun
{
    return MigrationRun::create([
        'name' => 'shaab',
        'db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => 'shaab',
        'db_username' => 'root', 'db_password' => 'x', 'table_prefix' => 'wp_',
        'source_facts' => [
            'site' => ['language' => 'ar'],
            'categories' => ['count' => 2, 'items' => [
                ['term_taxonomy_id' => 100, 'term_id' => 10, 'name' => 'اقتصاد', 'slug' => 'eq', 'parent' => 0, 'count' => 200, 'total_count' => 200],
                ['term_taxonomy_id' => 101, 'term_id' => 11, 'name' => 'كتاب وآراء', 'slug' => 'op', 'parent' => 0, 'count' => 50, 'total_count' => 50],
            ]],
        ],
    ]);
}

function cmToken(string ...$roles): string
{
    $u = User::factory()->create();
    foreach ($roles as $r) {
        $u->assignRole($r);
    }

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function cmCat(string $scope, string $slug, string $locale = 'ar'): Category
{
    return Category::create(['locale' => $locale, 'scope' => $scope, 'name' => 'cat-'.$slug, 'slug' => $slug]);
}

it('saves explicit category mappings with scope-valid targets', function (): void {
    $run = cmRun();
    $news = cmCat('news', 'n1');
    $opinion = cmCat('opinion', 'o1');
    $token = cmToken('super_admin');

    $this->withToken($token)
        ->putJson("/api/v1/admin/wp-migration/runs/{$run->id}/category-maps", [
            'maps' => [
                ['wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'wp_slug' => 'eq', 'wp_count' => 200, 'mode' => 'news', 'target_category_id' => $news->id],
                ['wp_term_id' => 11, 'wp_name' => 'كتاب وآراء', 'wp_slug' => 'op', 'wp_count' => 50, 'mode' => 'articles', 'target_category_id' => $opinion->id],
                ['wp_term_id' => 99, 'wp_name' => 'سلايدر', 'mode' => 'exclude'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.count', 3)
        ->assertJsonPath('data.included', 2);

    $m = MigrationCategoryMap::where('run_id', $run->id)->where('wp_term_id', 10)->first();
    expect($m->mode)->toBe(WpCategoryMode::News);
    expect($m->target_category_id)->toBe($news->id);

    $ex = MigrationCategoryMap::where('run_id', $run->id)->where('wp_term_id', 99)->first();
    expect($ex->mode)->toBe(WpCategoryMode::Exclude);
    expect($ex->target_category_id)->toBeNull();
});

it('is idempotent — re-saving updates instead of duplicating', function (): void {
    $run = cmRun();
    $news = cmCat('news', 'n1');
    $both = cmCat('both', 'b1');
    $token = cmToken('super_admin');

    $payload = fn (int $target): array => ['maps' => [
        ['wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'mode' => 'news', 'target_category_id' => $target],
    ]];

    $this->withToken($token)->putJson("/api/v1/admin/wp-migration/runs/{$run->id}/category-maps", $payload($news->id))->assertOk();
    $this->withToken($token)->putJson("/api/v1/admin/wp-migration/runs/{$run->id}/category-maps", $payload($both->id))->assertOk();

    $rows = MigrationCategoryMap::where('run_id', $run->id)->where('wp_term_id', 10)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->target_category_id)->toBe($both->id);
});

it('rejects a target whose scope conflicts with the chosen type (no partial write)', function (): void {
    $run = cmRun();
    $news = cmCat('news', 'n1');
    $token = cmToken('super_admin');

    $this->withToken($token)
        ->putJson("/api/v1/admin/wp-migration/runs/{$run->id}/category-maps", [
            'maps' => [['wp_term_id' => 11, 'wp_name' => 'x', 'mode' => 'articles', 'target_category_id' => $news->id]],
        ])
        ->assertStatus(422);

    expect(MigrationCategoryMap::where('run_id', $run->id)->count())->toBe(0);
});

it('requires a target for included categories', function (): void {
    $run = cmRun();
    $token = cmToken('super_admin');

    $this->withToken($token)
        ->putJson("/api/v1/admin/wp-migration/runs/{$run->id}/category-maps", [
            'maps' => [['wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'mode' => 'news']],
        ])
        ->assertStatus(422);
});

it('returns target category pools filtered by scope and locale', function (): void {
    $run = cmRun();
    cmCat('news', 'n1');
    cmCat('opinion', 'o1');
    cmCat('both', 'b1');
    cmCat('news', 'enn', 'en'); // مستبعد باللغة

    $token = cmToken('super_admin');

    $res = $this->withToken($token)
        ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/target-categories")
        ->assertOk();

    expect($res->json('data.locale'))->toBe('ar');
    expect($res->json('data.news'))->toHaveCount(2);     // news + both
    expect($res->json('data.articles'))->toHaveCount(2); // opinion + both
});

it('merges source categories with saved mappings', function (): void {
    $run = cmRun();
    $news = cmCat('news', 'n1');
    $token = cmToken('super_admin');

    $this->withToken($token)->putJson("/api/v1/admin/wp-migration/runs/{$run->id}/category-maps", [
        'maps' => [['wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'mode' => 'news', 'target_category_id' => $news->id]],
    ])->assertOk();

    $items = collect(
        $this->withToken($token)
            ->getJson("/api/v1/admin/wp-migration/runs/{$run->id}/categories")
            ->assertOk()
            ->json('data.items')
    );

    $eco = $items->firstWhere('term_id', 10);
    expect($eco['mode'])->toBe('news');
    expect($eco['target_category_id'])->toBe($news->id);
    expect($eco['total_count'])->toBe(200);

    $other = $items->firstWhere('term_id', 11);
    expect($other['mode'])->toBe('exclude'); // غير مُنسَّب بعد
});

it('requires wp-migration.manage to save category maps', function (): void {
    $run = cmRun();
    $news = cmCat('news', 'n1');
    $token = cmToken('editor'); // admin role, no wp-migration permission

    $this->withToken($token)
        ->putJson("/api/v1/admin/wp-migration/runs/{$run->id}/category-maps", [
            'maps' => [['wp_term_id' => 10, 'wp_name' => 'x', 'mode' => 'news', 'target_category_id' => $news->id]],
        ])
        ->assertStatus(403);
});
