<?php

declare(strict_types=1);

use App\Actions\Admin\WpMigration\SeedLedgerAction;
use App\Enums\MigrationRunStatus;
use App\Jobs\WpMigration\DispatchMigrationChunkJob;
use App\Jobs\WpMigration\ImportWpPostJob;
use App\Models\Category;
use App\Models\MigrationCategoryMap;
use App\Models\MigrationItem;
use App\Models\MigrationRun;
use App\Models\User;
use App\Support\WpMigration\MigrationAuthor;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Queue::fake();
});

// ─── Fixtures ───────────────────────────────────────────────────────────────

/**
 * مصدر مُصغّر لتعداد اللقطة. ttid 100/101 مُضمَّنان، 102 مُستبعد.
 *  1 publish→100، 2 publish→101، 3 publish→100+101 (يُعدّ مرّة)،
 *  4 draft→100 (يُستبعد)، 5 publish→102 (غير مُضمَّن)، 6 publish→100+102 (يُعدّ مرّة).
 *  ⇒ المؤهَّلون: 1،2،3،6 (الإجمالي 4).
 */
function seedSource(): Connection
{
    config(['database.connections.wp_seed' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]]);
    DB::purge('wp_seed');
    $c = DB::connection('wp_seed');
    $s = $c->getSchemaBuilder();

    $s->create('posts', function (Blueprint $t): void {
        $t->id('ID');
        $t->string('post_type')->default('post');
        $t->string('post_status')->default('publish');
    });
    $s->create('term_relationships', function (Blueprint $t): void {
        $t->unsignedBigInteger('object_id');
        $t->unsignedBigInteger('term_taxonomy_id');
    });

    $c->table('posts')->insert([
        ['ID' => 1, 'post_type' => 'post', 'post_status' => 'publish'],
        ['ID' => 2, 'post_type' => 'post', 'post_status' => 'publish'],
        ['ID' => 3, 'post_type' => 'post', 'post_status' => 'publish'],
        ['ID' => 4, 'post_type' => 'post', 'post_status' => 'draft'],
        ['ID' => 5, 'post_type' => 'post', 'post_status' => 'publish'],
        ['ID' => 6, 'post_type' => 'post', 'post_status' => 'publish'],
    ]);
    $c->table('term_relationships')->insert([
        ['object_id' => 1, 'term_taxonomy_id' => 100],
        ['object_id' => 2, 'term_taxonomy_id' => 101],
        ['object_id' => 3, 'term_taxonomy_id' => 100],
        ['object_id' => 3, 'term_taxonomy_id' => 101],
        ['object_id' => 4, 'term_taxonomy_id' => 100],
        ['object_id' => 5, 'term_taxonomy_id' => 102],
        ['object_id' => 6, 'term_taxonomy_id' => 100],
        ['object_id' => 6, 'term_taxonomy_id' => 102],
    ]);

    return $c;
}

function seedRun(string $status = 'ready'): MigrationRun
{
    $news = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'اقتصاد', 'slug' => 'eco-'.uniqid()]);
    $opinion = Category::create(['locale' => 'ar', 'scope' => 'opinion', 'name' => 'رأي', 'slug' => 'op-'.uniqid()]);

    $run = MigrationRun::create([
        'name' => 'shaab', 'status' => $status, 'db_host' => '127.0.0.1', 'db_port' => 3306,
        'db_name' => 'shaab', 'db_username' => 'root', 'db_password' => 'x', 'table_prefix' => '',
        'uploads_path' => sys_get_temp_dir(),
        'source_facts' => ['site' => ['language' => 'ar'], 'categories' => ['items' => [
            ['term_id' => 10, 'term_taxonomy_id' => 100, 'total_count' => 5],
            ['term_id' => 11, 'term_taxonomy_id' => 101, 'total_count' => 2],
            ['term_id' => 12, 'term_taxonomy_id' => 102, 'total_count' => 9],
        ]]],
    ]);

    MigrationCategoryMap::insert([
        ['run_id' => $run->id, 'wp_term_id' => 10, 'wp_name' => 'اقتصاد', 'mode' => 'news', 'target_category_id' => $news->id, 'created_at' => now(), 'updated_at' => now()],
        ['run_id' => $run->id, 'wp_term_id' => 11, 'wp_name' => 'رأي', 'mode' => 'articles', 'target_category_id' => $opinion->id, 'created_at' => now(), 'updated_at' => now()],
        ['run_id' => $run->id, 'wp_term_id' => 12, 'wp_name' => 'منوّعات', 'mode' => 'exclude', 'target_category_id' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    return $run;
}

function execToken(string ...$roles): string
{
    $u = User::factory()->create();
    foreach ($roles as $r) {
        $u->assignRole($r);
    }

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** عنصر دفتر مباشر (لا مصدر) لاختبار المُوزِّع على حالة الدفتر وحدها. */
function ledgerItem(MigrationRun $run, int $wpId, string $status, int $attempts = 0, ?DateTimeInterface $updatedAt = null): MigrationItem
{
    $item = MigrationItem::create([
        'run_id' => $run->id, 'wp_post_id' => $wpId, 'status' => $status, 'attempts' => $attempts,
    ]);
    if ($updatedAt !== null) {
        // طابع تحديث قديم لاختبار الاسترداد (تجاوز timestamps التلقائي).
        MigrationItem::query()->where('id', $item->id)->update(['updated_at' => $updatedAt]);
    }

    return $item->fresh();
}

// ─── Ledger snapshot (#1) ─────────────────────────────────────────────────────

it('seeds only eligible published posts in included categories, deduped (#1)', function (): void {
    $run = seedRun();

    $total = (new SeedLedgerAction(seedSource()))->handle($run);

    expect($total)->toBe(4);
    expect($run->fresh()->total_items)->toBe(4);

    $ids = MigrationItem::query()->where('run_id', $run->id)->pluck('wp_post_id')->sort()->values()->all();
    expect($ids)->toBe([1, 2, 3, 6]); // 4 مسودّة و5 (ttid مُستبعد) خارج اللقطة
    expect(MigrationItem::query()->where('run_id', $run->id)->where('status', 'pending')->count())->toBe(4);
});

it('is idempotent — re-seeding the ledger never duplicates (#1/#11)', function (): void {
    $run = seedRun();
    $conn = seedSource();

    (new SeedLedgerAction($conn))->handle($run);
    $second = (new SeedLedgerAction($conn))->handle($run);

    expect($second)->toBe(4);
    expect(MigrationItem::query()->where('run_id', $run->id)->count())->toBe(4);
});

it('seeds zero items when no category is included', function (): void {
    $run = MigrationRun::create([
        'name' => 'empty', 'status' => 'ready', 'db_host' => '127.0.0.1', 'db_name' => 'shaab',
        'db_username' => 'root', 'db_password' => 'x', 'table_prefix' => '',
        'source_facts' => ['categories' => ['items' => []]],
    ]);

    $total = (new SeedLedgerAction(seedSource()))->handle($run);

    expect($total)->toBe(0);
    expect($run->fresh()->total_items)->toBe(0);
});

// ─── Incremental seeding (opt-in): new posts only by wp_post_id watermark ──────

it('incremental mode seeds only posts above the highest wp_post_id from prior runs (new only)', function (): void {
    // تشغيلة سابقة بذرت حتى wp_post_id = 3 (العلامة المائيّة من هوية المصدر).
    $prior = seedRun();
    ledgerItem($prior, 1, 'done');
    ledgerItem($prior, 2, 'done');
    ledgerItem($prior, 3, 'done');

    // تشغيلة تزايديّة جديدة على نفس المصدر ⇒ «الجديد فقط» (ID > 3).
    $run = seedRun();
    $total = (new SeedLedgerAction(seedSource(), incremental: true))->handle($run);

    expect($total)->toBe(1);
    $ids = MigrationItem::query()->where('run_id', $run->id)->pluck('wp_post_id')->all();
    expect($ids)->toBe([6]); // 1/2/3 مُرحَّلة سابقاً، 4 مسودّة، 5 ttid مُستبعد
});

it('incremental mode on the first run (no prior items) seeds everything, like full mode', function (): void {
    $run = seedRun();
    $total = (new SeedLedgerAction(seedSource(), incremental: true))->handle($run);

    expect($total)->toBe(4); // لا تشغيلة سابقة ⇒ العلامة 0 ⇒ بذر كامل (سلوك غير متغيّر)
});

it('incremental watermark counts only imported posts (done/partial), not seeded-but-stuck ones', function (): void {
    // تشغيلة سابقة: 1،2 استُورِدا فعلاً (done)، و6 بُذِر فقط وعَلِق دون استيراد (pending).
    // العلامة يجب أن تكون 2 (أعلى مُستورَد) لا 6 — كي لا يُحجَب الخبر الجديد 6.
    $prior = seedRun();
    ledgerItem($prior, 1, 'done');
    ledgerItem($prior, 2, 'done');
    ledgerItem($prior, 6, 'pending');

    $run = seedRun();
    $total = (new SeedLedgerAction(seedSource(), incremental: true))->handle($run);

    expect($total)->toBe(2); // يُبذَر 3 و6 (ID > 2)؛ لو حُسِبت 6 المبذورة لكان 0
    $ids = MigrationItem::query()->where('run_id', $run->id)->pluck('wp_post_id')->sort()->values()->all();
    expect($ids)->toBe([3, 6]);
});

// ─── Chunked dispatch + claim (#2/#8) ─────────────────────────────────────────

it('dispatches one import job per eligible item, claims them queued, and reschedules itself', function (): void {
    $run = seedRun('running');
    ledgerItem($run, 1, 'pending');
    ledgerItem($run, 2, 'pending');
    ledgerItem($run, 3, 'failed', attempts: 1); // فاشل تحت السقف ⇒ يُعاد توزيعه

    (new DispatchMigrationChunkJob($run->id))->handle();

    Queue::assertPushed(ImportWpPostJob::class, 3);
    Queue::assertPushed(DispatchMigrationChunkJob::class, 1); // إعادة جدولة الذات

    expect(MigrationItem::query()->where('run_id', $run->id)->where('status', 'queued')->count())->toBe(3);
});

it('does not redispatch failed items that exhausted the attempts cap (#5)', function (): void {
    config(['wp-migration.item_tries' => 3]);
    $run = seedRun('running');
    ledgerItem($run, 1, 'pending');
    ledgerItem($run, 2, 'failed', attempts: 3); // بلغ السقف ⇒ لا يُعاد توزيعه

    (new DispatchMigrationChunkJob($run->id))->handle();

    Queue::assertPushed(ImportWpPostJob::class, 1); // المعلّق فقط
    expect(MigrationItem::query()->where('wp_post_id', 2)->where('run_id', $run->id)->first()->status->value)->toBe('failed');
});

// ─── Pause / stop semantics (#6) ──────────────────────────────────────────────

it('stops dispatching new work when the run is not running (#6)', function (): void {
    $run = seedRun('paused');
    ledgerItem($run, 1, 'pending');

    (new DispatchMigrationChunkJob($run->id))->handle();

    Queue::assertNotPushed(ImportWpPostJob::class);
    Queue::assertNotPushed(DispatchMigrationChunkJob::class); // لا إعادة جدولة وهي موقوفة
    expect(MigrationItem::query()->where('wp_post_id', 1)->where('run_id', $run->id)->first()->status->value)->toBe('pending');
});

// ─── Stale reclaim (#4) ───────────────────────────────────────────────────────

it('reclaims abandoned in-flight items after the stale lock window (#4)', function (): void {
    config(['wp-migration.stale_lock_minutes' => 15]);
    $run = seedRun('paused'); // موقوفة: يحدث الاسترداد دون توزيع جديد لعزل السلوك
    ledgerItem($run, 1, 'processing', updatedAt: now()->subMinutes(20)); // عالق
    ledgerItem($run, 2, 'processing', updatedAt: now()->subMinutes(2));  // حديث

    (new DispatchMigrationChunkJob($run->id))->handle();

    expect(MigrationItem::query()->where('wp_post_id', 1)->where('run_id', $run->id)->first()->status->value)->toBe('pending');
    expect(MigrationItem::query()->where('wp_post_id', 2)->where('run_id', $run->id)->first()->status->value)->toBe('processing');
});

// ─── Atomic counter recompute (#10) ───────────────────────────────────────────

it('recomputes progress counters atomically from the ledger (#10)', function (): void {
    config(['wp-migration.item_tries' => 3]);
    $run = seedRun('running');
    ledgerItem($run, 1, 'done');
    ledgerItem($run, 2, 'done');
    ledgerItem($run, 3, 'partial');
    ledgerItem($run, 4, 'failed', attempts: 3);
    ledgerItem($run, 5, 'skipped');
    ledgerItem($run, 6, 'pending');

    (new DispatchMigrationChunkJob($run->id))->handle();

    $run->refresh();
    expect($run->done_items)->toBe(2);
    expect($run->partial_items)->toBe(1);
    expect($run->failed_items)->toBe(1);
    expect($run->skipped_items)->toBe(1);
    expect($run->processed_items)->toBe(5); // done+partial+failed+skipped
});

// ─── Completion vs. waiting on in-flight ──────────────────────────────────────

it('marks the run completed when no work remains and nothing is in flight', function (): void {
    config(['wp-migration.item_tries' => 3]);
    $run = seedRun('running');
    ledgerItem($run, 1, 'done');
    ledgerItem($run, 2, 'skipped');
    ledgerItem($run, 3, 'failed', attempts: 3); // فشل دائم — لا يمنع الاكتمال

    (new DispatchMigrationChunkJob($run->id))->handle();

    $run->refresh();
    expect($run->status)->toBe(MigrationRunStatus::Completed);
    expect($run->finished_at)->not->toBeNull();
    Queue::assertNotPushed(DispatchMigrationChunkJob::class);
});

it('waits (reschedules without completing) while items are still in flight', function (): void {
    $run = seedRun('running');
    ledgerItem($run, 1, 'done');
    ledgerItem($run, 2, 'processing', updatedAt: now()); // طائر حديث

    (new DispatchMigrationChunkJob($run->id))->handle();

    $run->refresh();
    expect($run->status)->toBe(MigrationRunStatus::Running); // لم تكتمل بعد
    Queue::assertPushed(DispatchMigrationChunkJob::class, 1);
    Queue::assertNotPushed(ImportWpPostJob::class);
});

// ─── Import job guards (#6/#8) ────────────────────────────────────────────────

it('import job is a no-op when the run is not running (#6)', function (): void {
    $run = seedRun('paused');
    $item = ledgerItem($run, 1, 'queued');

    (new ImportWpPostJob($run->id, $item->id))->handle();

    expect($item->fresh()->status->value)->toBe('queued'); // لم يُطالَب به
});

it('import job claim is idempotent — a finished item is never reprocessed (#8)', function (): void {
    $run = seedRun('running');
    $item = ledgerItem($run, 1, 'done');

    (new ImportWpPostJob($run->id, $item->id))->handle();

    expect($item->fresh()->status->value)->toBe('done'); // الحارس صفر صفوف ⇒ خروج مبكّر
});

// ─── Execution gate enforcement (#9) ──────────────────────────────────────────

it('start is blocked when the run is not approved/executable (#9)', function (): void {
    $run = seedRun('ready'); // لا سياسة/معاينة/اعتماد

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/start")
        ->assertStatus(422);

    expect($run->fresh()->status)->toBe(MigrationRunStatus::Ready);
    Queue::assertNotPushed(DispatchMigrationChunkJob::class);
});

it('start fails fast when the canonical author is missing (#8)', function (): void {
    $run = approvedRun();
    // لا ننشئ «كتاب الموقع»

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/start")
        ->assertStatus(422)
        ->assertJsonPath('message', __('wp_migration.run.author_missing'));
});

it('start fails when the uploads path is unreadable (preflight #5)', function (): void {
    MigrationAuthor::ensure();
    $run = approvedRun();
    $run->forceFill(['uploads_path' => sys_get_temp_dir().'/wp-missing-'.uniqid()])->save();

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/start")
        ->assertStatus(422)
        ->assertJsonPath('message', __('wp_migration.run.uploads_unreadable'));
});

it('start fails when the source is unreachable', function (): void {
    MigrationAuthor::ensure();
    $run = approvedRun();
    $run->forceFill(['db_host' => '127.0.0.1', 'db_port' => 1])->save(); // منفذ مغلق ⇒ تعذّر اتصال

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/start")
        ->assertStatus(422)
        ->assertJsonPath('message', __('wp_migration.connection.failed'));
});

// ⚠️ TEMPORARY FEATURE — Quick Incremental Import — Remove before Production release.
// (يُثبت: العلم المعطَّل ⇒ 403، وبوّابة الاعتماد المُخفَّفة ⇒ 422 — تحقّق خلفيّ.)

it('quick-incremental shortcut is rejected when disabled (production gate)', function (): void {
    config(['wp-migration.quick_incremental' => false]);
    $run = approvedRun();

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/quick-incremental")
        ->assertStatus(403);

    Queue::assertNotPushed(DispatchMigrationChunkJob::class);
});

it('quick-incremental requires a prior approval (policy + approved_at)', function (): void {
    config(['wp-migration.quick_incremental' => true]);
    $run = seedRun('ready'); // بلا سياسة/اعتماد

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/quick-incremental")
        ->assertStatus(422);

    Queue::assertNotPushed(DispatchMigrationChunkJob::class);
});

// ─── Lifecycle transitions ────────────────────────────────────────────────────

it('pauses a running run', function (): void {
    $run = seedRun('running');

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'paused');

    expect($run->fresh()->status)->toBe(MigrationRunStatus::Paused);
});

it('rejects pausing a run that is not running', function (): void {
    $run = seedRun('ready');

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/pause")
        ->assertStatus(422);
});

it('resumes a paused run — resets queued items and redispatches (#11)', function (): void {
    $run = seedRun('paused');
    ledgerItem($run, 1, 'queued'); // طُولِب به ولم يُعالَج وقت الإيقاف
    ledgerItem($run, 2, 'done');

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/resume")
        ->assertOk()
        ->assertJsonPath('data.status', 'running');

    expect($run->fresh()->status)->toBe(MigrationRunStatus::Running);
    expect(MigrationItem::query()->where('wp_post_id', 1)->where('run_id', $run->id)->first()->status->value)->toBe('pending');
    expect(MigrationItem::query()->where('wp_post_id', 2)->where('run_id', $run->id)->first()->status->value)->toBe('done');
    Queue::assertPushed(DispatchMigrationChunkJob::class, 1);
});

it('stops a running run (safe stop #7)', function (): void {
    $run = seedRun('running');

    $this->withToken(execToken('super_admin'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/stop")
        ->assertOk()
        ->assertJsonPath('data.status', 'stopping');

    expect($run->fresh()->status)->toBe(MigrationRunStatus::Stopping);
});

// ─── RBAC ─────────────────────────────────────────────────────────────────────

it('requires wp-migration.manage to start execution', function (): void {
    $run = seedRun('ready');

    $this->withToken(execToken('editor'))
        ->postJson("/api/v1/admin/wp-migration/runs/{$run->id}/start")
        ->assertStatus(403);
});

/** تشغيلة عابرة للبوّابة (سياسة + معاينة حالية + اعتماد) — مسار وسائط صالح. */
function approvedRun(): MigrationRun
{
    $run = seedRun('ready');
    $run->forceFill([
        'conflict_policy' => 'prefer_news',
        'preview_generated_at' => now()->subMinute(),
        'mappings_updated_at' => now()->subMinutes(2),
        'approved_at' => now(),
        'uploads_path' => sys_get_temp_dir(),
    ])->save();

    return $run->fresh();
}
