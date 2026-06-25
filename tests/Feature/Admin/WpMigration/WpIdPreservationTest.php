<?php

declare(strict_types=1);

use App\Actions\Admin\WpMigration\ImportTaxonomyAction;
use App\Actions\Admin\WpMigration\ImportWpPostAction;
use App\Enums\ConflictPolicy;
use App\Models\Article;
use App\Models\ArticleUrlHistory;
use App\Models\Category;
use App\Models\MigrationCategoryMap;
use App\Models\MigrationItem;
use App\Models\MigrationRun;
use App\Support\WpMigration\MigrationAuthor;
use App\Support\WpMigration\MigrationExcerpt;
use App\Support\WpMigration\WpMediaImporter;
use App\Support\WpMigration\WpMediaResolver;
use App\Support\WpMigration\WpPostReader;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Minimal read-only WP source with a HIGH post id + post_modified (for ID/date preservation). */
function wpidSource(int $postId, ?string $excerpt, string $content): Connection
{
    config(['database.connections.wp_src' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]]);
    DB::purge('wp_src');
    $c = DB::connection('wp_src');
    $s = $c->getSchemaBuilder();

    $s->create('posts', function (Blueprint $t): void {
        $t->id('ID');
        $t->string('post_type')->default('post');
        $t->string('post_status')->default('publish');
        $t->longText('post_content')->nullable();
        $t->string('post_title')->nullable();
        $t->text('post_excerpt')->nullable();
        $t->string('post_name')->nullable();
        $t->string('post_date_gmt')->nullable();
        $t->string('post_modified_gmt')->nullable();
    });
    $s->create('postmeta', function (Blueprint $t): void {
        $t->id('meta_id');
        $t->unsignedBigInteger('post_id');
        $t->string('meta_key');
        $t->text('meta_value')->nullable();
    });
    $s->create('term_taxonomy', function (Blueprint $t): void {
        $t->id('term_taxonomy_id');
        $t->unsignedBigInteger('term_id');
        $t->string('taxonomy');
        $t->unsignedBigInteger('parent')->default(0);
    });
    $s->create('term_relationships', function (Blueprint $t): void {
        $t->unsignedBigInteger('object_id');
        $t->unsignedBigInteger('term_taxonomy_id');
    });

    $c->table('posts')->insert([[
        'ID' => $postId, 'post_type' => 'post', 'post_status' => 'publish',
        'post_title' => 'عنوان مُرحَّل', 'post_name' => 'migrated-post',
        'post_excerpt' => $excerpt, 'post_content' => $content,
        'post_date_gmt' => '2026-05-14 08:00:00', 'post_modified_gmt' => '2026-05-20 09:30:00',
    ]]);
    $c->table('term_taxonomy')->insert([['term_taxonomy_id' => 200, 'term_id' => 20, 'taxonomy' => 'category', 'parent' => 0]]);
    $c->table('term_relationships')->insert([['object_id' => $postId, 'term_taxonomy_id' => 200]]);

    return $c;
}

function wpidRun(): MigrationRun
{
    return MigrationRun::create([
        'name' => 'shaab', 'db_host' => '127.0.0.1', 'db_name' => 'shaab', 'db_username' => 'root',
        'db_password' => 'x', 'table_prefix' => '', 'uploads_path' => sys_get_temp_dir(),
        'conflict_policy' => 'prefer_news',
        'source_facts' => ['site' => ['language' => 'ar', 'url' => 'https://shaab.test']],
    ]);
}

function wpidImport(Connection $wp, int $newsCatId): ImportWpPostAction
{
    return new ImportWpPostAction(
        new WpPostReader($wp, 'https://shaab.test'),
        new WpMediaImporter(new WpMediaResolver(sys_get_temp_dir()), MigrationAuthor::resolve()),
        MigrationAuthor::id(),
        ConflictPolicy::from('prefer_news'),
        [200 => ['type' => 'news', 'target' => $newsCatId, 'weight' => 1, 'term_id' => 20]],
        'ar',
    );
}

beforeEach(function (): void {
    Storage::fake('uploads');
    Queue::fake();
    MigrationAuthor::ensure();
    $this->news = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'أخبار', 'slug' => 'news-cat']);
});

// ─── #1 Article ID preservation ───────────────────────────────────────────────

it('preserves the original WordPress post ID as the article ID', function (): void {
    $wp = wpidSource(342506, 'موجز صريح', '<p>نص الخبر</p>');
    $run = wpidRun();
    $item = MigrationItem::create(['run_id' => $run->id, 'wp_post_id' => 342506, 'status' => 'pending']);

    wpidImport($wp, $this->news->id)->handle($item);

    expect($item->fresh()->article_id)->toBe(342506);          // tracked id == WP id
    $article = Article::find(342506);
    expect($article)->not->toBeNull();
    expect($article->id)->toBe(342506);                        // EXACT original id (#1)
    expect($article->slug)->toBe('migrated-post');             // source slug (#5)
});

it('keeps the same article ID when re-imported (cross-run adopt-by-id, no duplicate)', function (): void {
    $wp = wpidSource(342506, 'موجز', '<p>نص</p>');
    $run = wpidRun();
    $item = MigrationItem::create(['run_id' => $run->id, 'wp_post_id' => 342506, 'status' => 'pending']);
    wpidImport($wp, $this->news->id)->handle($item);

    // Fresh run/item for the SAME source post — must adopt the existing id 342506, not duplicate.
    $run2 = wpidRun();
    $item2 = MigrationItem::create(['run_id' => $run2->id, 'wp_post_id' => 342506, 'status' => 'pending']);
    wpidImport($wp, $this->news->id)->handle($item2);

    expect($item2->fresh()->article_id)->toBe(342506);
    expect(Article::where('locale', 'ar')->count())->toBe(1); // no duplicate
});

// ─── #4 SEO excerpt: generate from body when WP excerpt is empty ───────────────

it('generates an Arabic-safe SEO excerpt from the body when the WP excerpt is empty', function (): void {
    $body = '<p>'.str_repeat('كلمةٌ عربيةٌ ', 60).'</p>'; // > 160 chars, multibyte
    $wp = wpidSource(342507, null, $body);
    $run = wpidRun();
    $item = MigrationItem::create(['run_id' => $run->id, 'wp_post_id' => 342507, 'status' => 'pending']);

    wpidImport($wp, $this->news->id)->handle($item);

    $excerpt = (string) Article::find(342507)->excerpt;
    expect($excerpt)->not->toBe('');
    expect(mb_check_encoding($excerpt, 'UTF-8'))->toBeTrue();   // no mid-codepoint cut
    expect(mb_strlen($excerpt, 'UTF-8'))->toBeLessThanOrEqual(161); // ~160 + ellipsis
    expect($excerpt)->toEndWith('…');
    expect($excerpt)->toStartWith('كلمة');
});

it('prefers the explicit WP excerpt when present', function (): void {
    $wp = wpidSource(342508, 'موجز ووردبريس الصريح', '<p>متن مختلف تماماً</p>');
    $run = wpidRun();
    $item = MigrationItem::create(['run_id' => $run->id, 'wp_post_id' => 342508, 'status' => 'pending']);

    wpidImport($wp, $this->news->id)->handle($item);

    expect(Article::find(342508)->excerpt)->toBe('موجز ووردبريس الصريح');
});

// ─── #5 Publish + updated date preservation ────────────────────────────────────

it('preserves the original publish and updated dates', function (): void {
    $wp = wpidSource(342509, 'م', '<p>ن</p>');
    $run = wpidRun();
    $item = MigrationItem::create(['run_id' => $run->id, 'wp_post_id' => 342509, 'status' => 'pending']);

    wpidImport($wp, $this->news->id)->handle($item);

    $article = Article::find(342509);
    expect($article->published_at?->format('Y-m-d H:i:s'))->toBe('2026-05-14 08:00:00'); // post_date_gmt
    expect($article->created_at?->format('Y-m-d H:i:s'))->toBe('2026-05-14 08:00:00');   // original publish
    expect($article->updated_at?->format('Y-m-d H:i:s'))->toBe('2026-05-20 09:30:00');   // post_modified_gmt
});

// ─── #7 Redirect: old URL → new resolves via the SAME id ───────────────────────

it('records a redirect from the legacy path resolving to the preserved id', function (): void {
    $wp = wpidSource(342510, 'م', '<p>ن</p>');
    $run = wpidRun();
    $item = MigrationItem::create(['run_id' => $run->id, 'wp_post_id' => 342510, 'status' => 'pending']);

    wpidImport($wp, $this->news->id)->handle($item);

    $redirect = ArticleUrlHistory::where('old_path', '/migrated-post')->first();
    expect($redirect)->not->toBeNull();
    expect($redirect->article_id)->toBe(342510); // legacy URL → preserved id
});

// ─── #2 Category ID preservation + #6 auto-increment realign + collision ───────

it('preserves the original WordPress term ID as the category ID', function (): void {
    $run = wpidRun();
    MigrationCategoryMap::create([
        'run_id' => $run->id, 'wp_term_id' => 55, 'wp_name' => 'سياسة', 'wp_slug' => 'politics',
        'wp_parent_id' => 0, 'mode' => 'news', 'disposition' => 'create',
    ]);

    $res = (new ImportTaxonomyAction)->handle($run);
    expect($res->getStatusCode())->toBe(200);

    $category = Category::find(55);
    expect($category)->not->toBeNull();
    expect($category->id)->toBe(55);          // EXACT original term id (#2)
    expect($category->name)->toBe('سياسة');
    // Relationship integrity (#3): the map target points at the preserved id.
    expect(MigrationCategoryMap::where('run_id', $run->id)->where('wp_term_id', 55)->value('target_category_id'))->toBe(55);
});

it('realigns the category auto-increment above the highest preserved id (#6)', function (): void {
    $run = wpidRun();
    MigrationCategoryMap::create(['run_id' => $run->id, 'wp_term_id' => 60, 'wp_name' => 'رياضة', 'wp_slug' => 'sport', 'wp_parent_id' => 0, 'mode' => 'news', 'disposition' => 'create']);

    (new ImportTaxonomyAction)->handle($run);

    // A new (non-migration) category must NOT collide with the reserved id range.
    $fresh = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'جديد', 'slug' => 'fresh-cat']);
    expect($fresh->id)->toBeGreaterThan(60);
});

it('refuses to create when a preserved category id is already taken (no regeneration)', function (): void {
    // An existing category occupies id 55.
    $occupant = new Category;
    $occupant->incrementing = false;
    $occupant->id = 55;
    $occupant->fill(['locale' => 'ar', 'scope' => 'news', 'name' => 'قائم', 'slug' => 'occupant']);
    $occupant->save();

    $run = wpidRun();
    MigrationCategoryMap::create(['run_id' => $run->id, 'wp_term_id' => 55, 'wp_name' => 'سياسة', 'wp_slug' => 'politics', 'wp_parent_id' => 0, 'mode' => 'news', 'disposition' => 'create']);

    $res = (new ImportTaxonomyAction)->handle($run);

    expect($res->getStatusCode())->toBe(422);
    expect(json_encode($res->getData(true)))->toContain('55');
    // The occupant is untouched; no duplicate created.
    expect(Category::find(55)->name)->toBe('قائم');
});

// ─── MigrationExcerpt unit (Arabic-safe) ───────────────────────────────────────

it('MigrationExcerpt strips shortcodes + tags and caps Arabic text safely', function (): void {
    $html = '[caption]تعليق[/caption]<p>'.str_repeat('نصٌّ ', 80).'</p>';
    $out = (string) MigrationExcerpt::make(null, $html);

    expect(mb_check_encoding($out, 'UTF-8'))->toBeTrue();
    expect($out)->not->toContain('[');
    expect($out)->not->toContain('<');
    expect(mb_strlen($out, 'UTF-8'))->toBeLessThanOrEqual(161);
});

it('MigrationExcerpt returns null when there is nothing to summarize', function (): void {
    expect(MigrationExcerpt::make(null, '   <p></p>  '))->toBeNull();
    expect(MigrationExcerpt::make('', ''))->toBeNull();
});
