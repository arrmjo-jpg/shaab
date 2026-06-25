<?php

declare(strict_types=1);

use App\Actions\Admin\WpMigration\ImportWpPostAction;
use App\Enums\ArticleType;
use App\Enums\ConflictPolicy;
use App\Models\Article;
use App\Models\ArticleUrlHistory;
use App\Models\Category;
use App\Models\MigrationItem;
use App\Models\MigrationRun;
use App\Support\WpMigration\MigrationAuthor;
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

function iwpPng(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
}

function iwpSource(): Connection
{
    config(['database.connections.wp_src' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]]);
    DB::purge('wp_src');
    $c = DB::connection('wp_src');
    $s = $c->getSchemaBuilder();

    $s->create('options', function (Blueprint $t): void {
        $t->id('option_id');
        $t->string('option_name');
        $t->text('option_value')->nullable();
    });
    $s->create('posts', function (Blueprint $t): void {
        $t->id('ID');
        $t->string('post_type')->default('post');
        $t->string('post_status')->default('publish');
        $t->string('post_mime_type')->default('');
        $t->longText('post_content')->nullable();
        $t->string('post_title')->nullable();
        $t->text('post_excerpt')->nullable();
        $t->string('post_name')->nullable();
        $t->string('post_date')->nullable();
        $t->string('post_date_gmt')->nullable();
        $t->string('guid')->nullable();
    });
    $s->create('postmeta', function (Blueprint $t): void {
        $t->id('meta_id');
        $t->unsignedBigInteger('post_id');
        $t->string('meta_key');
        $t->text('meta_value')->nullable();
    });
    $s->create('terms', function (Blueprint $t): void {
        $t->id('term_id');
        $t->string('name');
        $t->string('slug');
    });
    $s->create('term_taxonomy', function (Blueprint $t): void {
        $t->id('term_taxonomy_id');
        $t->unsignedBigInteger('term_id');
        $t->string('taxonomy');
        $t->unsignedBigInteger('parent')->default(0);
        $t->unsignedBigInteger('count')->default(0);
    });
    $s->create('term_relationships', function (Blueprint $t): void {
        $t->unsignedBigInteger('object_id');
        $t->unsignedBigInteger('term_taxonomy_id');
    });
    $s->create('yoast_indexable', function (Blueprint $t): void {
        $t->id();
        $t->unsignedBigInteger('object_id')->nullable();
        $t->string('object_type')->nullable();
        $t->string('title')->nullable();
        $t->text('description')->nullable();
        $t->string('primary_focus_keyword')->nullable();
        $t->string('permalink')->nullable();
        $t->string('canonical')->nullable();
        $t->integer('is_robots_noindex')->default(0);
    });

    $c->table('options')->insert([['option_name' => 'siteurl', 'option_value' => 'https://shaab.test']]);
    $c->table('posts')->insert([
        ['ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'خبر اقتصادي', 'post_name' => 'iqtisad', 'post_excerpt' => 'موجز', 'post_date_gmt' => '2024-01-01 10:00:00', 'post_content' => '<p>نص <strong>مهم</strong></p><figure><img src="https://shaab.test/wp-content/uploads/2024/01/inline.jpg" alt="صورة"></figure>'],
        ['ID' => 2, 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'مسودة', 'post_name' => 'draft', 'post_excerpt' => null, 'post_date_gmt' => null, 'post_content' => 'x'],
        ['ID' => 3, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'متعارض', 'post_name' => 'conflict', 'post_excerpt' => null, 'post_date_gmt' => null, 'post_content' => '<p>نص</p>'],
        ['ID' => 50, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'feat', 'post_name' => null, 'post_excerpt' => null, 'post_date_gmt' => null, 'post_content' => ''],
    ]);
    $c->table('postmeta')->insert([
        ['post_id' => 1, 'meta_key' => '_thumbnail_id', 'meta_value' => '50'],
        ['post_id' => 1, 'meta_key' => 'post_subtitle', 'meta_value' => 'عنوان فرعي'],
        ['post_id' => 1, 'meta_key' => '_yoast_wpseo_primary_category', 'meta_value' => '10'],
        ['post_id' => 50, 'meta_key' => '_wp_attached_file', 'meta_value' => '2024/01/feat.jpg'],
    ]);
    $c->table('terms')->insert([
        ['term_id' => 10, 'name' => 'اقتصاد', 'slug' => 'eco'],
        ['term_id' => 11, 'name' => 'رأي', 'slug' => 'opinion'],
    ]);
    $c->table('term_taxonomy')->insert([
        ['term_taxonomy_id' => 100, 'term_id' => 10, 'taxonomy' => 'category', 'parent' => 0, 'count' => 5],
        ['term_taxonomy_id' => 101, 'term_id' => 11, 'taxonomy' => 'category', 'parent' => 0, 'count' => 2],
    ]);
    $c->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 100],
        ['object_id' => 3, 'term_taxonomy_id' => 100],
        ['object_id' => 3, 'term_taxonomy_id' => 101],
    ]);
    $c->table('yoast_indexable')->insert([[
        'object_id' => 1, 'object_type' => 'post', 'title' => 'سيو اقتصاد', 'description' => 'وصف اقتصادي',
        'primary_focus_keyword' => 'اقتصاد', 'permalink' => 'https://shaab.test/iqtisad/', 'canonical' => 'https://shaab.test/iqtisad/', 'is_robots_noindex' => 0,
    ]]);

    return $c;
}

beforeEach(function (): void {
    Storage::fake('uploads');
    Queue::fake();
    config(['media-library.disk_name' => 'uploads']);

    $this->uploads = sys_get_temp_dir().'/iwp-up-'.uniqid();
    @mkdir($this->uploads.'/2024/01', 0777, true);
    file_put_contents($this->uploads.'/2024/01/feat.jpg', iwpPng());
    file_put_contents($this->uploads.'/2024/01/inline.jpg', iwpPng()."\x01"); // مختلف عن البارزة

    $this->wp = iwpSource();
    MigrationAuthor::ensure();
    $this->news = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'اقتصاد محلي', 'slug' => 'eco-cat']);
    $this->opinion = Category::create(['locale' => 'ar', 'scope' => 'opinion', 'name' => 'آراء', 'slug' => 'op-cat']);

    $this->run = MigrationRun::create([
        'name' => 'shaab', 'db_host' => '127.0.0.1', 'db_name' => 'shaab', 'db_username' => 'root',
        'db_password' => 'x', 'table_prefix' => '', 'uploads_path' => $this->uploads,
        'conflict_policy' => 'prefer_news',
        'source_facts' => ['site' => ['language' => 'ar', 'url' => 'https://shaab.test'], 'categories' => ['items' => [
            ['term_id' => 10, 'term_taxonomy_id' => 100, 'total_count' => 5],
            ['term_id' => 11, 'term_taxonomy_id' => 101, 'total_count' => 2],
        ]]],
    ]);

    $this->mapByTtid = [
        100 => ['type' => 'news', 'target' => $this->news->id, 'weight' => 5, 'term_id' => 10],
        101 => ['type' => 'articles', 'target' => $this->opinion->id, 'weight' => 2, 'term_id' => 11],
    ];
});

afterEach(function (): void {
    foreach ((array) glob($this->uploads.'/2024/01/*') as $f) {
        @unlink($f);
    }
    @rmdir($this->uploads.'/2024/01');
    @rmdir($this->uploads.'/2024');
    @rmdir($this->uploads);
});

function iwpAction(string $policy = 'prefer_news'): ImportWpPostAction
{
    return new ImportWpPostAction(
        new WpPostReader(test()->wp, 'https://shaab.test'),
        new WpMediaImporter(new WpMediaResolver(test()->uploads), MigrationAuthor::resolve()),
        MigrationAuthor::id(),
        ConflictPolicy::from($policy),
        test()->mapByTtid,
        'ar',
    );
}

it('imports a published post end-to-end: article, categories, media, SEO, redirect', function (): void {
    $item = MigrationItem::create(['run_id' => $this->run->id, 'wp_post_id' => 1, 'status' => 'pending']);

    iwpAction()->handle($item);

    $item->refresh();
    expect($item->status->value)->toBe('done');
    expect($item->article_id)->not->toBeNull();
    expect($item->target_type)->toBe('news');

    $article = Article::find($item->article_id);
    expect($article->type)->toBe(ArticleType::News);
    expect($article->status->value)->toBe('published');
    expect($article->locale)->toBe('ar');
    expect($article->author_id)->toBe(MigrationAuthor::id());           // #8 author lock
    expect($article->primary_category_id)->toBe($this->news->id);
    expect($article->title)->toBe('خبر اقتصادي');
    expect($article->subtitle)->toBe('عنوان فرعي');
    expect($article->slug)->toBe('iqtisad');                            // #6 source slug
    expect($article->seo_title)->toBe('سيو اقتصاد');                    // Yoast SEO
    expect($article->og_image_id)->not->toBeNull();                     // featured imported

    // مراقبة (Phase 7): عنوان المصدر مُلتقَط + عدّادات الوسائط (inline 1 + بارزة 1).
    expect($item->source_title)->toBe('خبر اقتصادي');
    expect($item->media_imported)->toBe(2);
    expect($item->media_reused)->toBe(0);
    expect($item->media_failed)->toBe(0);

    // content_json هو المصدر القانوني + HTML مشتقّ (#7).
    expect($article->content_json['type'])->toBe('doc');
    expect($article->content)->toContain('<strong>مهم</strong>');

    // تحويل من permalink الفعلي لـ Yoast (#5) بلا تكرار (#9).
    expect(ArticleUrlHistory::where('article_id', $article->id)->where('old_path', '/iqtisad/')->count())->toBe(1);
});

it('is idempotent — re-running updates the same article (identity = wp_post_id, #1/#10)', function (): void {
    $item = MigrationItem::create(['run_id' => $this->run->id, 'wp_post_id' => 1, 'status' => 'pending']);

    iwpAction()->handle($item);
    $firstArticleId = $item->fresh()->article_id;

    iwpAction()->handle($item->fresh());

    expect($item->fresh()->article_id)->toBe($firstArticleId);
    expect(Article::where('locale', 'ar')->count())->toBe(1);                          // لا تكرار
    expect(ArticleUrlHistory::where('old_path', '/iqtisad/')->count())->toBe(1);        // لا تكرار تحويل (#9)
});

it('classifies a missing/deleted source post as source_read_failed (#1)', function (): void {
    $item = MigrationItem::create(['run_id' => $this->run->id, 'wp_post_id' => 999, 'status' => 'pending']);

    iwpAction()->handle($item);

    expect($item->fresh()->status->value)->toBe('failed');
    expect($item->fresh()->last_error)->toContain('source_read_failed');
});

it('enforces published-only scope (draft is not imported)', function (): void {
    $item = MigrationItem::create(['run_id' => $this->run->id, 'wp_post_id' => 2, 'status' => 'pending']);

    iwpAction()->handle($item);

    expect($item->fresh()->status->value)->toBe('failed'); // draft → read null → source_read_failed
    expect(Article::query()->count())->toBe(0);
});

it('skips a conflicted post under the exclude policy (no mixed-type import)', function (): void {
    $item = MigrationItem::create(['run_id' => $this->run->id, 'wp_post_id' => 3, 'status' => 'pending']);

    iwpAction('exclude')->handle($item);

    expect($item->fresh()->status->value)->toBe('skipped');
    expect($item->fresh()->flags['reason'])->toBe('category_type_conflict');
    expect(Article::query()->count())->toBe(0);
});

it('resolves a conflicted post to News under prefer_news', function (): void {
    $item = MigrationItem::create(['run_id' => $this->run->id, 'wp_post_id' => 3, 'status' => 'pending']);

    iwpAction('prefer_news')->handle($item);

    $item->refresh();
    expect(in_array($item->status->value, ['done', 'partial'], true))->toBeTrue();
    expect($item->target_type)->toBe('news');
    expect(Article::find($item->article_id)->type)->toBe(ArticleType::News);
});
