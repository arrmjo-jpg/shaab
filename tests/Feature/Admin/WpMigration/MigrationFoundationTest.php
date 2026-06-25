<?php

declare(strict_types=1);

use App\Enums\ArticleType;
use App\Enums\ConflictPolicy;
use App\Enums\MigrationItemStatus;
use App\Enums\MigrationRunStatus;
use App\Enums\WpCategoryMode;
use App\Models\Category;
use App\Models\MigrationCategoryMap;
use App\Models\MigrationItem;
use App\Models\MigrationMedia;
use App\Models\MigrationRun;
use App\Models\User;
use App\Support\WpMigration\MigrationAuthor;
use App\Support\WpMigration\WpSourceConnection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function makeRun(array $attrs = []): MigrationRun
{
    return MigrationRun::create(array_merge([
        'name' => 'WordPress shaab',
        'db_host' => '127.0.0.1',
        'db_port' => 3306,
        'db_name' => 'shaab',
        'db_username' => 'root',
        'db_password' => 'sekret-pass',
        'table_prefix' => '3b5qs_',
        'uploads_path' => 'C:/wp/uploads',
        'source_facts' => ['published_posts' => 83947, 'attachments' => 94727],
    ], $attrs));
}

// ─── Ledger persistence (casts, encryption, resumability key) ─────────────────

it('persists a run with encrypted password, enum + json casts', function (): void {
    $run = makeRun();

    expect($run->fresh()->status)->toBe(MigrationRunStatus::Draft);   // DB default
    expect($run->source_facts['published_posts'])->toBe(83947);        // json cast
    expect($run->fresh()->db_password)->toBe('sekret-pass');           // encrypted round-trip

    // العمود المُخزَّن مُعمّى فعلاً (ليس النص الصريح).
    $raw = DB::table('wp_migration_runs')->where('id', $run->id)->value('db_password');
    expect($raw)->not->toBe('sekret-pass');
    expect($raw)->not->toBeNull();

    // سياسة التعارض تُحسَم في معاينة الأثر — null افتراضياً (لا تنفيذ) ثم enum.
    expect($run->fresh()->conflict_policy)->toBeNull();
    $run->update(['conflict_policy' => ConflictPolicy::PreferNews->value]);
    expect($run->fresh()->conflict_policy)->toBe(ConflictPolicy::PreferNews);
});

it('tracks items + media + category maps with enum casts and relations', function (): void {
    $run = makeRun();

    $item = MigrationItem::create([
        'run_id' => $run->id,
        'wp_post_id' => 12345,
        'status' => MigrationItemStatus::Partial->value,
        'flags' => ['near_empty_body' => false, 'unresolved_media' => ['x.jpg']],
        'attempts' => 1,
    ]);
    expect($item->status)->toBe(MigrationItemStatus::Partial);
    expect($item->status->isResumable())->toBeTrue();
    expect($item->flags['unresolved_media'])->toBe(['x.jpg']);
    expect($item->run->is($run))->toBeTrue();

    $newsCat = Category::create(['locale' => 'ar', 'scope' => 'news', 'name' => 'اقتصاد', 'slug' => 'eqtisad']);
    $opinionCat = Category::create(['locale' => 'ar', 'scope' => 'opinion', 'name' => 'كتاب وآراء', 'slug' => 'kottab-w-araa']);

    // تنسيب صريح: كل تصنيف مصدر يُسنَد لنوع واحد فقط (أخبار أو مقالات) → هدف واحد.
    $newsMap = MigrationCategoryMap::create([
        'run_id' => $run->id,
        'wp_term_id' => 7,
        'wp_name' => 'اقتصاد',
        'wp_slug' => 'eqtisad',
        'wp_count' => 6905,
        'mode' => WpCategoryMode::News->value,
        'target_category_id' => $newsCat->id,
    ]);
    expect($newsMap->mode)->toBe(WpCategoryMode::News);
    expect($newsMap->mode->articleType())->toBe(ArticleType::News);
    expect($newsMap->target->is($newsCat))->toBeTrue();

    $articleMap = MigrationCategoryMap::create([
        'run_id' => $run->id,
        'wp_term_id' => 8,
        'wp_name' => 'كتاب وآراء',
        'wp_slug' => 'kottab',
        'wp_count' => 4508,
        'mode' => WpCategoryMode::Articles->value,
        'target_category_id' => $opinionCat->id,
    ]);
    expect($articleMap->mode->articleType())->toBe(ArticleType::Opinion);
    expect($articleMap->target->is($opinionCat))->toBeTrue();

    $media = MigrationMedia::create([
        'run_id' => $run->id,
        'source_key' => 'att:555',
        'wp_attachment_id' => 555,
        'source_url' => 'http://localhost/wp-content/uploads/2024/01/x.jpg',
        'status' => 'pending',
    ]);
    expect($media->run->is($run))->toBeTrue();

    expect($run->items()->count())->toBe(1);
    expect($run->media()->count())->toBe(1);
    expect($run->categoryMaps()->count())->toBe(2);
});

it('enforces unique (run_id, wp_post_id) — the dedup/resume backbone', function (): void {
    $run = makeRun();
    MigrationItem::create(['run_id' => $run->id, 'wp_post_id' => 99]);

    expect(fn () => MigrationItem::create(['run_id' => $run->id, 'wp_post_id' => 99]))
        ->toThrow(QueryException::class);
});

// ─── Dynamic source connection ────────────────────────────────────────────────

it('registers a runtime WordPress source connection from run config', function (): void {
    $run = makeRun();
    $name = WpSourceConnection::configure($run);

    expect($name)->toBe('wp_source');
    expect(config('database.connections.wp_source.host'))->toBe('127.0.0.1');
    expect(config('database.connections.wp_source.database'))->toBe('shaab');
    expect(config('database.connections.wp_source.username'))->toBe('root');
    expect(config('database.connections.wp_source.password'))->toBe('sekret-pass'); // decrypted
    expect(config('database.connections.wp_source.prefix'))->toBe('3b5qs_');         // WP table prefix
    expect(config('database.connections.wp_source.driver'))->toBe('mysql');
});

// ─── Canonical author (NON-NEGOTIABLE: كتاب الموقع) ───────────────────────────

it('ensure() creates the canonical author «كتاب الموقع» idempotently', function (): void {
    expect(User::where('name', 'كتاب الموقع')->count())->toBe(0);

    $first = MigrationAuthor::ensure();
    $second = MigrationAuthor::ensure();

    expect($first->id)->toBe($second->id);
    expect($first->name)->toBe('كتاب الموقع');
    expect($first->is_writer)->toBeTrue();
    expect($first->isActive())->toBeTrue();
    expect(User::where('name', 'كتاب الموقع')->count())->toBe(1);
});

it('resolve()/id() fail fast when the canonical author is missing (#8)', function (): void {
    expect(MigrationAuthor::exists())->toBeFalse();
    expect(fn () => MigrationAuthor::id())->toThrow(RuntimeException::class);
});

it('resolves an existing «كتاب الموقع» user without creating a duplicate', function (): void {
    $manual = User::factory()->create(['name' => 'كتاب الموقع']);

    expect(MigrationAuthor::exists())->toBeTrue();
    expect(MigrationAuthor::id())->toBe($manual->id);
    expect(User::where('name', 'كتاب الموقع')->count())->toBe(1);
});

// ─── RBAC ─────────────────────────────────────────────────────────────────────

it('seeds wp-migration permissions and grants them to super_admin', function (): void {
    seedRoles();

    expect(Permission::where('name', 'wp-migration.view')->exists())->toBeTrue();
    expect(Permission::where('name', 'wp-migration.manage')->exists())->toBeTrue();

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    expect($user->can('wp-migration.view'))->toBeTrue();
    expect($user->can('wp-migration.manage'))->toBeTrue();
});
