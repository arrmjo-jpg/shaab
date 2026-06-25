<?php

declare(strict_types=1);

use App\Enums\ArticleType;
use App\Jobs\WpMigration\DispatchMigrationChunkJob;
use App\Jobs\WpMigration\ImportWpPostJob;
use App\Models\Article;
use App\Models\ArticleUrlHistory;
use App\Models\Category;
use App\Models\MigrationItem;
use App\Models\MigrationRun;
use App\Models\User;
use App\Support\WpMigration\MigrationAuthor;
use App\Support\WpMigration\WpSourceConnection;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * تحقّق الترحيل من النهاية للنهاية (Phase 8) — يقود دورة الحياة الكاملة عبر نقاط
 * النهاية والمهام الحقيقية مقابل مصدر ووردبريس مُزيَّف (sqlite). لا mysql حيّ في
 * البيئة، فهذا أقوى إثبات ممكن للسلامة التشغيلية: تدقيق → خرائط → معاينة → اعتماد →
 * بدء → معالجة → إيقاف/استئناف → إعادة محاولة → اكتمال → تقرير، + الاستئناف الحتميّ
 * وحقن الفشل وسلامة المخرجات والبوّابات الأمنية.
 */
function e2ePng(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
}

function e2eSource(): Connection
{
    config(['database.connections.wp_e2e' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]]);
    DB::purge('wp_e2e');
    $c = DB::connection('wp_e2e');
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
        ['ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'خبر اقتصادي عاجل', 'post_name' => 'iqtisad', 'post_excerpt' => 'موجز', 'post_date_gmt' => '2024-01-01 10:00:00', 'post_content' => '<p>نصّ <strong>مهمّ</strong> بالعربية.</p><figure><img src="https://shaab.test/wp-content/uploads/2024/01/inline.jpg" alt="صورة"></figure>'],
        ['ID' => 2, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'مقال رأي حُرّ', 'post_name' => 'rai', 'post_excerpt' => null, 'post_date_gmt' => '2024-02-01 10:00:00', 'post_content' => '<p>رأيٌ بلا سيو.</p>'],
        ['ID' => 3, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'منشور متعارض', 'post_name' => 'conflict', 'post_excerpt' => null, 'post_date_gmt' => '2024-03-01 10:00:00', 'post_content' => '<p>متعارض.</p>'],
        ['ID' => 4, 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'مسودّة', 'post_name' => 'draft', 'post_excerpt' => null, 'post_date_gmt' => null, 'post_content' => 'x'],
        ['ID' => 5, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'منوّعات', 'post_name' => 'misc', 'post_excerpt' => null, 'post_date_gmt' => null, 'post_content' => '<p>غير مُنسَّب.</p>'],
        ['ID' => 50, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'feat', 'post_name' => null, 'post_excerpt' => null, 'post_date_gmt' => null, 'post_content' => ''],
    ]);
    $c->table('postmeta')->insert([
        ['post_id' => 1, 'meta_key' => '_thumbnail_id', 'meta_value' => '50'],
        ['post_id' => 1, 'meta_key' => '_yoast_wpseo_primary_category', 'meta_value' => '10'],
        ['post_id' => 50, 'meta_key' => '_wp_attached_file', 'meta_value' => '2024/01/feat.jpg'],
    ]);
    $c->table('terms')->insert([
        ['term_id' => 10, 'name' => 'اقتصاد', 'slug' => 'eco'],
        ['term_id' => 11, 'name' => 'رأي', 'slug' => 'opinion'],
        ['term_id' => 12, 'name' => 'منوّعات', 'slug' => 'misc'],
    ]);
    $c->table('term_taxonomy')->insert([
        ['term_taxonomy_id' => 100, 'term_id' => 10, 'taxonomy' => 'category', 'parent' => 0, 'count' => 2],
        ['term_taxonomy_id' => 101, 'term_id' => 11, 'taxonomy' => 'category', 'parent' => 0, 'count' => 2],
        ['term_taxonomy_id' => 102, 'term_id' => 12, 'taxonomy' => 'category', 'parent' => 0, 'count' => 1],
    ]);
    $c->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 100],
        ['object_id' => 2, 'term_taxonomy_id' => 101],
        ['object_id' => 3, 'term_taxonomy_id' => 100],
        ['object_id' => 3, 'term_taxonomy_id' => 101],
        ['object_id' => 4, 'term_taxonomy_id' => 100],
        ['object_id' => 5, 'term_taxonomy_id' => 102],
    ]);
    $c->table('yoast_indexable')->insert([[
        'object_id' => 1, 'object_type' => 'post', 'title' => 'سيو اقتصاد', 'description' => 'وصف اقتصادي',
        'primary_focus_keyword' => 'اقتصاد', 'permalink' => 'https://shaab.test/iqtisad/', 'canonical' => 'https://shaab.test/iqtisad/', 'is_robots_noindex' => 0,
    ]]);

    return $c;
}

beforeEach(function (): void {
    seedRoles();
    Queue::fake();
    Storage::fake('uploads');
    config(['media-library.disk_name' => 'uploads']);

    $this->uploads = sys_get_temp_dir().'/e2e-up-'.uniqid();
    @mkdir($this->uploads.'/2024/01', 0777, true);
    file_put_contents($this->uploads.'/2024/01/feat.jpg', e2ePng());
    file_put_contents($this->uploads.'/2024/01/inline.jpg', e2ePng()."\x01");

    $this->wp = e2eSource();
    WpSourceConnection::fake($this->wp);

    MigrationAuthor::ensure();
    $this->news = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'اقتصاد محلي', 'slug' => 'eco-cat']);
    $this->opinion = Category::create(['locale' => 'ar', 'scope' => 'opinion', 'name' => 'آراء', 'slug' => 'op-cat']);

    $u = User::factory()->create();
    $u->assignRole('super_admin');
    $this->token = $u->createToken('admin', ['admin'])->plainTextToken;
});

afterEach(function (): void {
    WpSourceConnection::forget();
    foreach ((array) glob($this->uploads.'/2024/01/*') as $f) {
        @unlink($f);
    }
    @rmdir($this->uploads.'/2024/01');
    @rmdir($this->uploads.'/2024');
    @rmdir($this->uploads);
});

const E2E = '/api/v1/admin/wp-migration';

/** يقود discovery→audit→map→preview→approve ويُعيد مُعرّف التشغيلة (لم تُبدأ بعد). */
function e2eApprove(): int
{
    $self = test();
    $runId = $self->withToken($self->token)->postJson(E2E.'/runs', [
        'name' => 'shaab-e2e', 'db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => 'shaab',
        'db_username' => 'root', 'db_password' => 'x', 'table_prefix' => '', 'uploads_path' => $self->uploads,
    ])->assertCreated()->json('data.id');

    $self->withToken($self->token)->postJson(E2E."/runs/{$runId}/audit")->assertOk();
    $self->withToken($self->token)->putJson(E2E."/runs/{$runId}/category-maps", [
        'maps' => [
            ['wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'mode' => 'news', 'target_category_id' => $self->news->id],
            ['wp_term_id' => 11, 'wp_name' => 'رأي', 'mode' => 'articles', 'target_category_id' => $self->opinion->id],
        ],
    ])->assertOk();
    $self->withToken($self->token)->postJson(E2E."/runs/{$runId}/preview")->assertOk();
    $self->withToken($self->token)->postJson(E2E."/runs/{$runId}/approve", ['conflict_policy' => 'prefer_news'])->assertOk();

    return (int) $runId;
}

function e2eStart(): MigrationRun
{
    $runId = e2eApprove();
    test()->withToken(test()->token)->postJson(E2E."/runs/{$runId}/start")->assertOk();

    return MigrationRun::findOrFail($runId);
}

/** يُفرِّغ الطابور يدوياً (sync يتجاهل delay فيتكرّر المُوزِّع ذاتيّ الجدولة بلا حدّ). */
function e2eDrain(MigrationRun $run, int $max = 40): void
{
    for ($i = 0; $i < $max; $i++) {
        (new DispatchMigrationChunkJob($run->id))->handle();
        $run->refresh();
        if ($run->status->value !== 'running') {
            return;
        }
        $queued = MigrationItem::query()->where('run_id', $run->id)
            ->where('status', 'queued')->pluck('id')->all();
        if ($queued === []) {
            (new DispatchMigrationChunkJob($run->id))->handle(); // نخطوة ختم الاكتمال
            $run->refresh();

            return;
        }
        foreach ($queued as $id) {
            (new ImportWpPostJob($run->id, (int) $id))->handle();
        }
    }
}

function e2eArticle(MigrationRun $run, int $wpPostId): ?Article
{
    $item = MigrationItem::query()->where('run_id', $run->id)->where('wp_post_id', $wpPostId)->first();

    return $item?->article_id ? Article::find($item->article_id) : null;
}

// ─── 1. Full lifecycle + data integrity (#1/#4) ───────────────────────────────

it('runs the entire lifecycle end-to-end and produces faithful articles', function (): void {
    $runId = e2eApprove();
    $run = MigrationRun::findOrFail($runId);

    // preview detected the conflict (post 3 in news + articles)
    expect($run->preview['totals']['conflicts'])->toBe(1);
    expect($run->preview['totals']['unique_posts'])->toBe(3);
    expect($run->can_execute ?? $run->canExecute())->toBeTrue();

    // start → seeds eligible (publish + mapped ttid): posts 1,2,3
    $this->withToken($this->token)->postJson(E2E."/runs/{$runId}/start")->assertOk();
    $run->refresh();
    expect($run->status->value)->toBe('running');
    expect($run->total_items)->toBe(3);

    e2eDrain($run);
    $run->refresh();
    expect($run->status->value)->toBe('completed');
    expect($run->finished_at)->not->toBeNull();

    // exactly 3 articles, all by the canonical author (no WP authors migrated)
    expect(Article::count())->toBe(3);
    expect(Article::query()->where('author_id', '!=', MigrationAuthor::id())->count())->toBe(0);

    // post 1 (News): content fidelity, encoding, inline rewrite, featured, SEO, slug, redirect, primary
    $a1 = e2eArticle($run, 1);
    expect($a1)->not->toBeNull();
    expect($a1->type)->toBe(ArticleType::News);
    expect($a1->status->value)->toBe('published');
    expect($a1->locale)->toBe('ar');
    expect($a1->title)->toBe('خبر اقتصادي عاجل');                    // Arabic intact
    expect($a1->slug)->toBe('iqtisad');                              // source slug
    expect($a1->content)->toContain('<strong>مهمّ</strong>');       // fidelity + encoding
    expect($a1->content)->not->toContain('shaab.test');             // inline image rewritten off-source
    expect($a1->seo_title)->toBe('سيو اقتصاد');                      // Yoast SEO
    expect($a1->og_image_id)->not->toBeNull();                      // featured imported
    expect($a1->primary_category_id)->toBe($this->news->id);
    expect(ArticleUrlHistory::query()->where('old_path', '/iqtisad/')->count())->toBe(1);

    // post 2 (Articles/Opinion): no Yoast → SEO null, still imports
    $a2 = e2eArticle($run, 2);
    expect($a2->type)->toBe(ArticleType::Opinion);
    expect($a2->seo_title)->toBeNull();
    expect($a2->og_image_id)->toBeNull();

    // post 3 (conflict, prefer_news) → News, only news-compatible category kept
    $a3 = e2eArticle($run, 3);
    expect($a3->type)->toBe(ArticleType::News);
    expect($a3->primary_category_id)->toBe($this->news->id);

    // report closure
    $this->withToken($this->token)->getJson(E2E."/runs/{$runId}/report")
        ->assertOk()
        ->assertJsonPath('data.is_complete', true)
        ->assertJsonPath('data.succeeded', 3)
        ->assertJsonPath('data.counts.failed', 0);

    // media accounting (post 1: inline + featured imported)
    $item1 = MigrationItem::query()->where('run_id', $run->id)->where('wp_post_id', 1)->first();
    expect($item1->media_imported)->toBe(2);
    expect($item1->source_title)->toBe('خبر اقتصادي عاجل');
});

// ─── 2. Resume / interruption (#2) ────────────────────────────────────────────

it('reclaims a crashed worker and resumes from the ledger with no duplicates', function (): void {
    $run = e2eStart();

    // dispatcher claims pending→queued (jobs are faked); process ONLY post 1, then "crash".
    (new DispatchMigrationChunkJob($run->id))->handle();
    (new ImportWpPostJob($run->id, (int) MigrationItem::query()
        ->where('run_id', $run->id)->where('wp_post_id', 1)->value('id')))->handle();

    // remaining items are stuck 'queued' (worker died) — backdate to trip the stale window.
    MigrationItem::query()->where('run_id', $run->id)->where('status', 'queued')
        ->update(['updated_at' => now()->subMinutes(30)]);

    e2eDrain($run);
    $run->refresh();

    expect($run->status->value)->toBe('completed');
    expect(Article::count())->toBe(3);
    // identity = wp_post_id: every eligible post maps to exactly one article (no dup).
    foreach ([1, 2, 3] as $wp) {
        expect(MigrationItem::query()->where('run_id', $run->id)->where('wp_post_id', $wp)->count())->toBe(1);
    }
});

it('is idempotent on restart after completion (re-seed adds nothing, no duplicate articles)', function (): void {
    $run = e2eStart();
    e2eDrain($run);
    expect(Article::count())->toBe(3);

    // restart a completed run → re-seed is insertOrIgnore, nothing pending → completes, no dup.
    $this->withToken($this->token)->postJson(E2E."/runs/{$run->id}/start")->assertOk();
    e2eDrain($run->fresh());

    expect(MigrationItem::query()->where('run_id', $run->id)->count())->toBe(3);
    expect(Article::count())->toBe(3);
});

it('pauses (halting dispatch) and resumes to completion', function (): void {
    $run = e2eStart();

    $this->withToken($this->token)->postJson(E2E."/runs/{$run->id}/pause")->assertOk();
    e2eDrain($run->fresh());
    expect($run->fresh()->status->value)->toBe('paused');
    expect(Article::count())->toBe(0); // nothing dispatched while paused

    $this->withToken($this->token)->postJson(E2E."/runs/{$run->id}/resume")->assertOk();
    e2eDrain($run->fresh());
    expect($run->fresh()->status->value)->toBe('completed');
    expect(Article::count())->toBe(3);
});

it('resumes a stopped run from the ledger', function (): void {
    $run = e2eStart();

    $this->withToken($this->token)->postJson(E2E."/runs/{$run->id}/stop")->assertOk();
    expect($run->fresh()->status->value)->toBe('stopping');
    e2eDrain($run->fresh()); // stopping → no new dispatch

    $this->withToken($this->token)->postJson(E2E."/runs/{$run->id}/resume")->assertOk();
    e2eDrain($run->fresh());
    expect($run->fresh()->status->value)->toBe('completed');
    expect(Article::count())->toBe(3);
});

// ─── 3. Failure injection + structured classification (#3) ────────────────────

it('classifies a source post deleted after queueing as source_read_failed', function (): void {
    $run = e2eStart();
    $this->wp->table('posts')->where('ID', 2)->delete(); // vanishes after the ledger snapshot

    e2eDrain($run);

    $item = MigrationItem::query()->where('run_id', $run->id)->where('wp_post_id', 2)->first();
    expect($item->status->value)->toBe('failed');
    expect($item->flags['reason'])->toBe('source_read_failed');
    expect(Article::count())->toBe(2); // posts 1 & 3 still succeed (poison isolation)
    expect($run->fresh()->status->value)->toBe('completed');
});

it('classifies a vanished target category as category_unresolved', function (): void {
    $run = e2eStart();
    // حذف تصنيف الهدف يتسلسل عبر مفتاح الخريطة (FK) فيصير المنشور غير مُنسَّب →
    // يُصنَّف category_unresolved ويُتخطّى (قابل للاسترداد بإعادة التنسيب + إعادة المحاولة).
    $this->opinion->forceDelete();

    e2eDrain($run);

    $item = MigrationItem::query()->where('run_id', $run->id)->where('wp_post_id', 2)->first();
    expect($item->flags['reason'])->toBe('category_unresolved');       // #5 classification
    expect(in_array($item->status->value, ['skipped', 'failed'], true))->toBeTrue();
    expect(Article::query()->where('id', $item->article_id)->exists())->toBeFalse(); // not imported
    expect(e2eArticle($run, 1))->not->toBeNull();                     // News path unaffected
});

// ─── 6. Security / gate enforcement (#6) ──────────────────────────────────────

it('refuses to start without a current approval (execution gate)', function (): void {
    // create + audit only — no preview/approve.
    $runId = $this->withToken($this->token)->postJson(E2E.'/runs', [
        'name' => 'shaab-e2e', 'db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => 'shaab',
        'db_username' => 'root', 'db_password' => 'x', 'table_prefix' => '', 'uploads_path' => $this->uploads,
    ])->json('data.id');
    $this->withToken($this->token)->postJson(E2E."/runs/{$runId}/audit")->assertOk();

    $this->withToken($this->token)->postJson(E2E."/runs/{$runId}/start")->assertStatus(422);
    expect(MigrationRun::findOrFail($runId)->status->value)->not->toBe('running');
});

it('refuses to start when the canonical author is missing (fail-fast)', function (): void {
    $runId = e2eApprove();
    User::query()->where('name', config('wp-migration.author_name'))->delete();

    $this->withToken($this->token)->postJson(E2E."/runs/{$runId}/start")
        ->assertStatus(422)
        ->assertJsonPath('message', __('wp_migration.run.author_missing'));
});
