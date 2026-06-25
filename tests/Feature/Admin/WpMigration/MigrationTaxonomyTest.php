<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\MigrationCategoryMap;
use App\Models\MigrationRun;
use App\Models\User;
use App\Support\WpMigration\WpCategoryMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    $u = User::factory()->create();
    $u->assignRole('super_admin');
    $this->token = $u->createToken('admin', ['admin'])->plainTextToken;
    $this->run = MigrationRun::create([
        'name' => 'shaab', 'status' => 'ready', 'db_host' => '127.0.0.1', 'db_name' => 'shaab',
        'db_username' => 'root', 'db_password' => 'x', 'table_prefix' => '',
        'source_facts' => ['site' => ['language' => 'ar'], 'categories' => ['items' => [
            ['term_id' => 10, 'term_taxonomy_id' => 100, 'total_count' => 5],
            ['term_id' => 20, 'term_taxonomy_id' => 200, 'total_count' => 3],
            ['term_id' => 30, 'term_taxonomy_id' => 300, 'total_count' => 2],
            ['term_id' => 40, 'term_taxonomy_id' => 400, 'total_count' => 1],
            ['term_id' => 50, 'term_taxonomy_id' => 500, 'total_count' => 1],
            ['term_id' => 60, 'term_taxonomy_id' => 600, 'total_count' => 1],
            ['term_id' => 70, 'term_taxonomy_id' => 700, 'total_count' => 1],
        ]]],
    ]);
});

const TAX = '/api/v1/admin/wp-migration';

/** @param  array<int,array<string,mixed>>  $maps */
function taxoSave(array $maps): TestResponse
{
    return test()->withToken(test()->token)->putJson(TAX.'/runs/'.test()->run->id.'/category-maps', ['maps' => $maps]);
}

function taxoImport(): TestResponse
{
    return test()->withToken(test()->token)->postJson(TAX.'/runs/'.test()->run->id.'/import-taxonomy');
}

function taxoMap(int $wpTermId, string $mode, string $disposition, int $parent = 0, ?string $slug = null, ?int $target = null): array
{
    return array_filter([
        'wp_term_id' => $wpTermId,
        'wp_name' => 'تصنيف '.$wpTermId,
        'wp_slug' => $slug,
        'wp_parent_id' => $parent,
        'mode' => $mode,
        'disposition' => $disposition,
        'target_category_id' => $target,
    ], fn ($v): bool => $v !== null);
}

function taxoCat(MigrationRun $run, int $wpTermId): ?Category
{
    $id = MigrationCategoryMap::query()->where('run_id', $run->id)->where('wp_term_id', $wpTermId)->value('created_category_id');

    return $id !== null ? Category::find($id) : null;
}

// ─── Deterministic creation rules (#5) ────────────────────────────────────────

it('creates a category deterministically (name, slug, scope, locale, status)', function (): void {
    taxoSave([['wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'wp_slug' => 'eco', 'wp_parent_id' => 0, 'mode' => 'news', 'disposition' => 'create']])->assertOk();

    taxoImport()->assertOk()->assertJsonPath('data.created', 1);

    $cat = taxoCat($this->run, 10);
    expect($cat)->not->toBeNull();
    expect($cat->name)->toBe('اقتصاد');
    expect($cat->slug)->toBe('eco');
    expect($cat->locale)->toBe('ar');
    expect($cat->scope->value)->toBe('news');
    expect($cat->status->value)->toBe('active');
    expect($cat->parent_id)->toBeNull();

    $map = MigrationCategoryMap::query()->where('run_id', $this->run->id)->where('wp_term_id', 10)->first();
    expect($map->target_category_id)->toBe($cat->id);   // downstream points to the created category
    expect($map->created_category_id)->toBe($cat->id);
});

it('derives scope from content type (articles → opinion)', function (): void {
    taxoSave([['wp_term_id' => 30, 'wp_name' => 'رأي', 'wp_slug' => 'opinion', 'mode' => 'articles', 'disposition' => 'create']])->assertOk();
    taxoImport()->assertOk();

    expect(taxoCat($this->run, 30)->scope->value)->toBe('opinion');
});

it('applies the deterministic -wp-{term_id} suffix on slug collision (never random)', function (): void {
    Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'موجود', 'slug' => 'eco']);

    taxoSave([['wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'wp_slug' => 'eco', 'mode' => 'news', 'disposition' => 'create']])->assertOk();
    taxoImport()->assertOk();

    expect(taxoCat($this->run, 10)->slug)->toBe('eco-wp-10');
});

// ─── Hierarchy preservation (#2) ──────────────────────────────────────────────

it('nests a child under its created same-type parent (parent created first)', function (): void {
    taxoSave([
        taxoMap(10, 'news', 'create', 0, 'p'),
        taxoMap(20, 'news', 'create', 10, 'c'),
    ])->assertOk();
    taxoImport()->assertOk()->assertJsonPath('data.created', 2);

    expect(taxoCat($this->run, 20)->parent_id)->toBe(taxoCat($this->run, 10)->id);
});

it('sends a child to root when the parent is a different type, mapped, or excluded', function (): void {
    $existing = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'قائم', 'slug' => 'exist']);

    taxoSave([
        taxoMap(30, 'articles', 'create', 0, 'a-parent'),  // different-type parent
        taxoMap(40, 'news', 'create', 30, 'a-child'),      // parent is articles → root
        taxoMap(50, 'news', 'create', 999, 'orphan'),      // parent not imported → root
        taxoMap(60, 'news', 'map', 0, null, $existing->id), // mapped parent
        taxoMap(70, 'news', 'create', 60, 'under-map'),    // parent is mapped → root
    ])->assertOk();
    taxoImport()->assertOk();

    expect(taxoCat($this->run, 40)->parent_id)->toBeNull();
    expect(taxoCat($this->run, 50)->parent_id)->toBeNull();
    expect(taxoCat($this->run, 70)->parent_id)->toBeNull();
});

it('caps nesting at MAX_DEPTH (4th level falls back to root)', function (): void {
    taxoSave([
        taxoMap(10, 'news', 'create', 0, 'l1'),
        taxoMap(20, 'news', 'create', 10, 'l2'),
        taxoMap(30, 'news', 'create', 20, 'l3'),
        taxoMap(40, 'news', 'create', 30, 'l4'),
    ])->assertOk();
    taxoImport()->assertOk();

    expect(taxoCat($this->run, 20)->parent_id)->toBe(taxoCat($this->run, 10)->id);
    expect(taxoCat($this->run, 30)->parent_id)->toBe(taxoCat($this->run, 20)->id);
    expect(taxoCat($this->run, 40)->parent_id)->toBeNull(); // would be depth 4 → root
});

// ─── Idempotency (#idempotency) ───────────────────────────────────────────────

it('is idempotent — re-running creates no duplicates and reuses created categories', function (): void {
    taxoSave([taxoMap(10, 'news', 'create', 0, 'eco')])->assertOk();
    taxoImport()->assertOk()->assertJsonPath('data.created', 1);
    $firstId = taxoCat($this->run, 10)->id;

    taxoImport()->assertOk()->assertJsonPath('data.created', 0)->assertJsonPath('data.reused', 1);

    expect(Category::query()->where('locale', 'ar')->count())->toBe(1);
    expect(taxoCat($this->run, 10)->id)->toBe($firstId);
});

it('restores a soft-deleted created category on re-run instead of duplicating', function (): void {
    taxoSave([taxoMap(10, 'news', 'create', 0, 'eco')])->assertOk();
    taxoImport()->assertOk();
    $cat = taxoCat($this->run, 10);
    $cat->delete(); // simulate rollback

    taxoImport()->assertOk()->assertJsonPath('data.reused', 1);

    expect(Category::query()->withTrashed()->where('locale', 'ar')->count())->toBe(1);
    expect(Category::find($cat->id)->trashed())->toBeFalse(); // restored
});

// ─── Mixed dispositions (#3) ──────────────────────────────────────────────────

it('handles a mix of create, map and exclude', function (): void {
    $existing = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'قائم', 'slug' => 'exist']);

    taxoSave([
        taxoMap(10, 'news', 'create', 0, 'new-one'),
        taxoMap(20, 'news', 'map', 0, null, $existing->id),
        taxoMap(30, 'exclude', 'exclude'),
    ])->assertOk();

    taxoImport()->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.mapped', 1)
        ->assertJsonPath('data.excluded', 1);

    expect(taxoCat($this->run, 10))->not->toBeNull();
    expect(MigrationCategoryMap::query()->where('run_id', $this->run->id)->where('wp_term_id', 20)->value('target_category_id'))->toBe($existing->id);
    expect(MigrationCategoryMap::query()->where('run_id', $this->run->id)->where('wp_term_id', 30)->value('created_category_id'))->toBeNull();
});

// ─── Preview / execution compatibility ────────────────────────────────────────

it('exposes created categories to the downstream pipeline via target linkage', function (): void {
    taxoSave([taxoMap(10, 'news', 'create', 0, 'eco')])->assertOk();
    taxoImport()->assertOk();

    // WpCategoryMap::build feeds seed + import + preview — it must resolve the created target.
    $build = WpCategoryMap::build($this->run->fresh());
    expect($build)->toHaveKey(100); // term 10 → ttid 100
    expect($build[100]['type'])->toBe('news');
    expect($build[100]['target'])->toBe(taxoCat($this->run, 10)->id);
});

// ─── Validation (#3/#4) ───────────────────────────────────────────────────────

it('accepts create without a target but requires one for map, and requires a type for included', function (): void {
    taxoSave([taxoMap(10, 'news', 'create')])->assertOk();                 // create → no target needed
    taxoSave([taxoMap(11, 'news', 'map')])->assertStatus(422);             // map → target required
    taxoSave([['wp_term_id' => 12, 'wp_name' => 'x', 'mode' => 'exclude', 'disposition' => 'create']])->assertStatus(422); // included → type required
});

// ─── Rollback safety (#rollback) ──────────────────────────────────────────────

it('makes created taxonomy traceable and never touches pre-existing categories', function (): void {
    $preexisting = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'سابق', 'slug' => 'pre']);

    taxoSave([
        taxoMap(10, 'news', 'create', 0, 'created-cat'),
        taxoMap(20, 'news', 'map', 0, null, $preexisting->id),
    ])->assertOk();
    taxoImport()->assertOk();

    // Traceability: only migration-created rows carry created_category_id.
    $createdIds = MigrationCategoryMap::query()->where('run_id', $this->run->id)
        ->whereNotNull('created_category_id')->pluck('created_category_id')->all();
    expect($createdIds)->toBe([taxoCat($this->run, 10)->id]); // the mapped (pre-existing) row is NOT listed

    // Rollback removes only the created set; pre-existing survives untouched.
    Category::query()->whereIn('id', $createdIds)->delete();
    expect(Category::find($preexisting->id))->not->toBeNull();
    expect(Category::find(taxoCat($this->run, 10)?->id ?? 0))->toBeNull();
});

// ─── RBAC ─────────────────────────────────────────────────────────────────────

it('requires wp-migration.manage to import taxonomy', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $token = $editor->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson(TAX."/runs/{$this->run->id}/import-taxonomy")->assertStatus(403);
});
