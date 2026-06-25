<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\MigrationCategoryMap;
use App\Models\MigrationRun;
use App\Models\User;
use App\Support\WpMigration\MigrationPreviewBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/**
 * مصدر ووردبريس مُصغّر للمعاينة. السيناريو:
 *  ttid 100=أخبار(term10)، 101=مقالات(term11)، 102=أخبار(term12).
 *  منشور1→100 (خبر)، 2→101 (مقال)، 3→100+101 (تعارض)،
 *  4→100+102 (خبر، نوع واحد رغم تصنيفين ⇒ يُعدّ مرّة)، 5 مسودّة (مُستبعد).
 */
function prevFixture(): Connection
{
    config(['database.connections.wp_prev' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]]);
    DB::purge('wp_prev');
    $conn = DB::connection('wp_prev');
    $s = $conn->getSchemaBuilder();

    $s->create('posts', function (Blueprint $t): void {
        $t->id('ID');
        $t->string('post_type')->default('post');
        $t->string('post_status')->default('publish');
        $t->longText('post_content')->nullable();
        $t->string('post_title')->nullable();
        $t->text('post_excerpt')->nullable();
    });
    $s->create('term_relationships', function (Blueprint $t): void {
        $t->unsignedBigInteger('object_id');
        $t->unsignedBigInteger('term_taxonomy_id');
    });
    $s->create('postmeta', function (Blueprint $t): void {
        $t->id('meta_id');
        $t->unsignedBigInteger('post_id');
        $t->string('meta_key');
        $t->text('meta_value')->nullable();
    });
    $s->create('yoast_indexable', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('object_id')->nullable();
        $t->string('object_type')->nullable();
        $t->string('title')->nullable();
        $t->text('description')->nullable();
    });

    $conn->table('posts')->insert([
        ['ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'نص خبر', 'post_title' => 'خبر ١', 'post_excerpt' => 'موجز ١'],
        ['ID' => 2, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'مقال رأي', 'post_title' => 'رأي ٢', 'post_excerpt' => null],
        ['ID' => 3, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'متعارض', 'post_title' => 'متعارض ٣', 'post_excerpt' => null],
        ['ID' => 4, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'نص <img src="x.jpg"> صورة', 'post_title' => 'خبر ٤', 'post_excerpt' => null],
        ['ID' => 5, 'post_type' => 'post', 'post_status' => 'draft', 'post_content' => 'مسودة', 'post_title' => 'مسودة', 'post_excerpt' => null],
    ]);
    $conn->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 100],
        ['object_id' => 2, 'term_taxonomy_id' => 101],
        ['object_id' => 3, 'term_taxonomy_id' => 100],
        ['object_id' => 3, 'term_taxonomy_id' => 101],
        ['object_id' => 4, 'term_taxonomy_id' => 100],
        ['object_id' => 4, 'term_taxonomy_id' => 102],
        ['object_id' => 5, 'term_taxonomy_id' => 100],
    ]);
    $conn->table('postmeta')->insert([
        ['post_id' => 1, 'meta_key' => '_thumbnail_id', 'meta_value' => '500'],
        ['post_id' => 2, 'meta_key' => '_thumbnail_id', 'meta_value' => '500'],
        ['post_id' => 3, 'meta_key' => '_thumbnail_id', 'meta_value' => '501'],
    ]);
    $conn->table('yoast_indexable')->insert([
        ['object_id' => 1, 'object_type' => 'post', 'title' => 'سيو ١', 'description' => 'وصف ١'],
        ['object_id' => 2, 'object_type' => 'post', 'title' => 'سيو ٢', 'description' => 'وصف ٢'],
    ]);

    return $conn;
}

function prevRun(): MigrationRun
{
    $news = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'اقتصاد', 'slug' => 'eq']);
    $opinion = Category::create(['locale' => 'ar', 'scope' => 'opinion', 'name' => 'كتاب وآراء', 'slug' => 'op']);

    $run = MigrationRun::create([
        'name' => 'shaab', 'db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => 'shaab',
        'db_username' => 'root', 'db_password' => 'x', 'table_prefix' => '',
        'source_facts' => ['site' => ['language' => 'ar'], 'categories' => ['count' => 3, 'items' => [
            ['term_taxonomy_id' => 100, 'term_id' => 10, 'name' => 'اقتصاد', 'slug' => 'eq', 'parent' => 0, 'count' => 2, 'total_count' => 2],
            ['term_taxonomy_id' => 101, 'term_id' => 11, 'name' => 'رأي', 'slug' => 'op', 'parent' => 0, 'count' => 1, 'total_count' => 1],
            ['term_taxonomy_id' => 102, 'term_id' => 12, 'name' => 'تقنية', 'slug' => 'tech', 'parent' => 0, 'count' => 1, 'total_count' => 1],
        ]]],
    ]);

    MigrationCategoryMap::insert([
        ['run_id' => $run->id, 'wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'mode' => 'news', 'target_category_id' => $news->id, 'created_at' => now(), 'updated_at' => now()],
        ['run_id' => $run->id, 'wp_term_id' => 11, 'wp_name' => 'رأي', 'mode' => 'articles', 'target_category_id' => $opinion->id, 'created_at' => now(), 'updated_at' => now()],
        ['run_id' => $run->id, 'wp_term_id' => 12, 'wp_name' => 'تقنية', 'mode' => 'news', 'target_category_id' => $news->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    return $run;
}

function prevToken(string ...$roles): string
{
    $u = User::factory()->create();
    foreach ($roles as $r) {
        $u->assignRole($r);
    }

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

// ─── Preview engine ───────────────────────────────────────────────────────────

it('computes unique counts, conflicts, deduped media, SEO and samples', function (): void {
    $run = prevRun();
    $preview = (new MigrationPreviewBuilder($run, prevFixture()))->build();

    // Unique counting (#1) + conflict definition (#2): post 4 in two NEWS cats counts once.
    expect($preview['totals']['unique_posts'])->toBe(4);
    expect($preview['totals']['news'])->toBe(2);       // posts 1, 4
    expect($preview['totals']['articles'])->toBe(1);   // post 2
    expect($preview['totals']['conflicts'])->toBe(1);  // post 3 (news + articles)

    // Deduped media (#3): thumbnails 500 (×2) + 501 → 2 unique.
    expect($preview['media']['featured_unique'])->toBe(2);
    expect($preview['media']['posts_with_inline'])->toBe(1); // post 4

    expect($preview['seo']['mapped'])->toBe(2);
    expect($preview['redirects']['estimated'])->toBe(4);
    expect($preview['warnings'])->toContain('conflicts');

    // Sample transform records (#4) — incl. a flagged conflict + canonical byline.
    expect($preview['samples'])->not->toBeEmpty();
    $conflict = collect($preview['samples'])->firstWhere('target.is_conflict', true);
    expect($conflict)->not->toBeNull();
    expect($conflict['target']['byline'])->toBe('كتاب الموقع');
});

// ─── Hard execution gate (#5) ─────────────────────────────────────────────────

it('enforces the hard execution gate via the model', function (): void {
    $run = prevRun();
    expect($run->canExecute())->toBeFalse();

    $run->forceFill([
        'conflict_policy' => 'prefer_news',
        'preview_generated_at' => now(),
        'mappings_updated_at' => now()->subMinute(),
        'approved_at' => now(),
    ])->save();
    $run->refresh();
    expect($run->previewIsCurrent())->toBeTrue();
    expect($run->isApproved())->toBeTrue();
    expect($run->canExecute())->toBeTrue();

    // تغيّر التنسيب بعد التوليد → قديمة → التنفيذ محجوب.
    $run->forceFill(['mappings_updated_at' => now()->addMinute()])->save();
    $run->refresh();
    expect($run->previewIsCurrent())->toBeFalse();
    expect($run->canExecute())->toBeFalse();
});

// ─── Approval + invalidation (#5, #6) ──────────────────────────────────────────

it('approve arms the gate when the preview is current', function (): void {
    $run = prevRun();
    $run->forceFill(['preview_generated_at' => now(), 'preview' => ['totals' => []]])->save();

    $this->withToken(prevToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/approve", ['conflict_policy' => 'prefer_news'])
        ->assertOk()
        ->assertJsonPath('data.approved', true)
        ->assertJsonPath('data.can_execute', true);
});

it('approve rejects a stale preview', function (): void {
    $run = prevRun();
    $run->forceFill([
        'preview_generated_at' => now()->subMinute(),
        'mappings_updated_at' => now(), // أحدث من المعاينة ⇒ قديمة
    ])->save();

    $this->withToken(prevToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/approve", ['conflict_policy' => 'prefer_news'])
        ->assertStatus(422);
});

it('marks the preview stale when category mappings change (#6)', function (): void {
    $run = prevRun();
    // فجوة زمنية واضحة: المعاينة قبل 5 دقائق، آخر تغيير تنسيب قبلها — حالية الآن.
    $run->forceFill(['preview_generated_at' => now()->subMinutes(5), 'mappings_updated_at' => now()->subMinutes(6)])->save();
    expect($run->fresh()->previewIsCurrent())->toBeTrue();

    $news = Category::query()->where('scope', 'news')->first();
    $this->withToken(prevToken('super_admin'))
        ->putJson("/api/v1/admin/wp-migration/runs/{$run->id}/category-maps", [
            'maps' => [['wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'mode' => 'news', 'target_category_id' => $news->id]],
        ])
        ->assertOk();

    expect($run->fresh()->previewIsCurrent())->toBeFalse();
});

it('preview generation requires at least one included mapping', function (): void {
    $run = MigrationRun::create([
        'name' => 'empty', 'db_host' => '127.0.0.1', 'db_name' => 'shaab', 'db_username' => 'root',
        'db_password' => 'x', 'table_prefix' => '', 'source_facts' => ['categories' => ['items' => []]],
    ]);

    $this->withToken(prevToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/preview")
        ->assertStatus(422);
});

it('requires wp-migration.manage to approve', function (): void {
    $run = prevRun();
    $run->forceFill(['preview_generated_at' => now()])->save();

    $this->withToken(prevToken('editor'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/approve", ['conflict_policy' => 'prefer_news'])
        ->assertStatus(403);
});

// ─── Prefixed-connection regression (real shaab uses prefix 3b5qs_) ─────────────

/** نفس بيانات prevFixture لكن باتصال ذي بادئة (wp_) — يحاكي مصدر ووردبريس الحقيقي. */
function prevFixturePrefixed(): Connection
{
    config(['database.connections.wp_prefixed' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wp_', 'foreign_key_constraints' => false,
    ]]);
    DB::purge('wp_prefixed');
    $conn = DB::connection('wp_prefixed');
    $s = $conn->getSchemaBuilder();

    $s->create('posts', function (Blueprint $t): void {
        $t->id('ID');
        $t->string('post_type')->default('post');
        $t->string('post_status')->default('publish');
        $t->longText('post_content')->nullable();
        $t->string('post_title')->nullable();
        $t->text('post_excerpt')->nullable();
    });
    $s->create('term_relationships', function (Blueprint $t): void {
        $t->unsignedBigInteger('object_id');
        $t->unsignedBigInteger('term_taxonomy_id');
    });
    $s->create('postmeta', function (Blueprint $t): void {
        $t->id('meta_id');
        $t->unsignedBigInteger('post_id');
        $t->string('meta_key');
        $t->text('meta_value')->nullable();
    });
    $s->create('yoast_indexable', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('object_id')->nullable();
        $t->string('object_type')->nullable();
        $t->string('title')->nullable();
        $t->text('description')->nullable();
    });

    $conn->table('posts')->insert([
        ['ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'نص خبر', 'post_title' => 'خبر ١', 'post_excerpt' => 'موجز ١'],
        ['ID' => 2, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'مقال رأي', 'post_title' => 'رأي ٢', 'post_excerpt' => null],
        ['ID' => 3, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'متعارض', 'post_title' => 'متعارض ٣', 'post_excerpt' => null],
        ['ID' => 4, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'نص <img src="x.jpg"> صورة', 'post_title' => 'خبر ٤', 'post_excerpt' => null],
        ['ID' => 5, 'post_type' => 'post', 'post_status' => 'draft', 'post_content' => 'مسودة', 'post_title' => 'مسودة', 'post_excerpt' => null],
    ]);
    $conn->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 100],
        ['object_id' => 2, 'term_taxonomy_id' => 101],
        ['object_id' => 3, 'term_taxonomy_id' => 100],
        ['object_id' => 3, 'term_taxonomy_id' => 101],
        ['object_id' => 4, 'term_taxonomy_id' => 100],
        ['object_id' => 4, 'term_taxonomy_id' => 102],
        ['object_id' => 5, 'term_taxonomy_id' => 100],
    ]);
    $conn->table('postmeta')->insert([
        ['post_id' => 1, 'meta_key' => '_thumbnail_id', 'meta_value' => '500'],
        ['post_id' => 2, 'meta_key' => '_thumbnail_id', 'meta_value' => '500'],
        ['post_id' => 3, 'meta_key' => '_thumbnail_id', 'meta_value' => '501'],
    ]);
    $conn->table('yoast_indexable')->insert([
        ['object_id' => 1, 'object_type' => 'post', 'title' => 'سيو ١', 'description' => 'وصف ١'],
        ['object_id' => 2, 'object_type' => 'post', 'title' => 'سيو ٢', 'description' => 'وصف ٢'],
    ]);

    return $conn;
}

it('builds the preview on a prefixed source connection (regression: raw alias prefixing)', function (): void {
    $run = prevRun();

    // قبل الإصلاح كان يرمي: Unknown column 'tr.object_id' (الـ alias يُبدَّل في الإنتاج فقط).
    $preview = (new MigrationPreviewBuilder($run, prevFixturePrefixed()))->build();

    expect($preview['totals']['unique_posts'])->toBe(4);
    expect($preview['totals']['news'])->toBe(2);
    expect($preview['totals']['articles'])->toBe(1);
    expect($preview['totals']['conflicts'])->toBe(1);
    expect($preview['media']['featured_unique'])->toBe(2);
    expect($preview['samples'])->not->toBeEmpty();
});
