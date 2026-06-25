<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\WpMigration\WpSourceInspector;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

/**
 * بنية مصدر ووردبريس مُصغّرة على اتصال sqlite (بادئة wptest_) — تُثبت منطق
 * الفاحص دون الحاجة لـ MySQL حيّ. الجداول تُنشأ فيزيائياً كـ wptest_* عبر بادئة الاتصال.
 */
function wmFixture(): Connection
{
    config(['database.connections.wp_fixture' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'wptest_',
        'foreign_key_constraints' => false,
    ]]);
    DB::purge('wp_fixture');

    $conn = DB::connection('wp_fixture');
    $schema = $conn->getSchemaBuilder();

    $schema->create('options', function (Blueprint $t): void {
        $t->id('option_id');
        $t->string('option_name');
        $t->text('option_value')->nullable();
    });
    $schema->create('posts', function (Blueprint $t): void {
        $t->id('ID');
        $t->string('post_type')->default('post');
        $t->string('post_status')->default('publish');
        $t->string('post_mime_type')->default('');
        $t->longText('post_content')->nullable();
        $t->string('post_title')->nullable();
    });
    $schema->create('postmeta', function (Blueprint $t): void {
        $t->id('meta_id');
        $t->unsignedBigInteger('post_id');
        $t->string('meta_key');
        $t->text('meta_value')->nullable();
    });
    $schema->create('terms', function (Blueprint $t): void {
        $t->id('term_id');
        $t->string('name');
        $t->string('slug');
    });
    $schema->create('term_taxonomy', function (Blueprint $t): void {
        $t->id('term_taxonomy_id');
        $t->unsignedBigInteger('term_id');
        $t->string('taxonomy');
        $t->unsignedBigInteger('parent')->default(0);
        $t->unsignedBigInteger('count')->default(0);
    });
    $schema->create('term_relationships', function (Blueprint $t): void {
        $t->unsignedBigInteger('object_id');
        $t->unsignedBigInteger('term_taxonomy_id');
    });
    $schema->create('users', function (Blueprint $t): void {
        $t->id('ID');
        $t->string('user_login');
    });
    $schema->create('yoast_indexable', function (Blueprint $t): void {
        $t->id();
        $t->string('object_type')->nullable();
    });

    $conn->table('options')->insert([
        ['option_name' => 'siteurl', 'option_value' => 'http://localhost/shaab'],
        ['option_name' => 'blogname', 'option_value' => 'صدى الشعب'],
        ['option_name' => 'WPLANG', 'option_value' => 'ar'],
    ]);
    $conn->table('posts')->insert([
        ['ID' => 1, 'post_type' => 'post', 'post_status' => 'publish', 'post_mime_type' => '', 'post_content' => '<!-- wp:paragraph --><p>اقتصاد</p><!-- /wp:paragraph -->', 'post_title' => 'خبر ١'],
        ['ID' => 2, 'post_type' => 'post', 'post_status' => 'publish', 'post_mime_type' => '', 'post_content' => '<p>نص <img src="x.jpg"> صورة</p>', 'post_title' => 'خبر ٢'],
        ['ID' => 3, 'post_type' => 'post', 'post_status' => 'publish', 'post_mime_type' => '', 'post_content' => 'نص عادي', 'post_title' => 'خبر ٣'],
        ['ID' => 4, 'post_type' => 'post', 'post_status' => 'draft', 'post_mime_type' => '', 'post_content' => 'مسودة', 'post_title' => 'مسودة'],
        ['ID' => 5, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_content' => '', 'post_title' => 'img1'],
        ['ID' => 6, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'post_content' => '', 'post_title' => 'img2'],
    ]);
    $conn->table('terms')->insert([
        ['term_id' => 10, 'name' => 'اقتصاد', 'slug' => '%d8%a7%d9%82%d8%aa%d8%b5%d8%a7%d8%af'],
        ['term_id' => 11, 'name' => 'كتاب وآراء', 'slug' => 'kottab'],
    ]);
    $conn->table('term_taxonomy')->insert([
        ['term_taxonomy_id' => 100, 'term_id' => 10, 'taxonomy' => 'category', 'parent' => 0, 'count' => 2],
        // term 11 طفل لـ term 10 — لاختبار الإجمالي الشامل للأبناء.
        ['term_taxonomy_id' => 101, 'term_id' => 11, 'taxonomy' => 'category', 'parent' => 10, 'count' => 1],
    ]);
    $conn->table('postmeta')->insert([
        ['post_id' => 1, 'meta_key' => '_thumbnail_id', 'meta_value' => '5'],
        ['post_id' => 2, 'meta_key' => '_thumbnail_id', 'meta_value' => '6'],
        ['post_id' => 1, 'meta_key' => 'sfly_guest_author_names', 'meta_value' => 'محمد'],
        ['post_id' => 1, 'meta_key' => '_yoast_wpseo_focuskw', 'meta_value' => 'اقتصاد'],
    ]);
    $conn->table('yoast_indexable')->insert([['object_type' => 'post']]);
    $conn->table('users')->insert([['user_login' => 'admin']]);

    return $conn;
}

function wmToken(string ...$roles): string
{
    $u = User::factory()->create();
    foreach ($roles as $r) {
        $u->assignRole($r);
    }

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

// ─── Inspector: detection ─────────────────────────────────────────────────────

it('detects the WordPress table prefix and confirms a WP install', function (): void {
    $inspector = new WpSourceInspector(wmFixture());

    expect($inspector->canConnect())->toBeTrue();
    expect($inspector->detectPrefix())->toBe('wptest_');
    expect($inspector->isWordpress())->toBeTrue();
});

it('reports canConnect=false for an unreachable source', function (): void {
    config(['database.connections.wp_bad' => [
        'driver' => 'sqlite',
        'database' => '/nonexistent-dir-'.uniqid().'/none.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);
    DB::purge('wp_bad');

    expect((new WpSourceInspector(DB::connection('wp_bad')))->canConnect())->toBeFalse();
});

// ─── Inspector: audit facts (read-only) ───────────────────────────────────────

it('gathers real source facts read-only', function (): void {
    $facts = (new WpSourceInspector(wmFixture()))->facts();

    expect($facts['prefix'])->toBe('wptest_');
    expect($facts['site']['name'])->toBe('صدى الشعب');
    expect($facts['site']['language'])->toBe('ar');

    expect($facts['posts']['published'])->toBe(3);
    expect($facts['posts']['draft'])->toBe(1);
    expect($facts['posts']['total'])->toBe(4);

    expect($facts['attachments']['total'])->toBe(2);
    expect(collect($facts['attachments']['by_mime'])->pluck('mime'))->toContain('image/jpeg');
    expect($facts['media']['featured_count'])->toBe(2);

    expect($facts['categories']['count'])->toBe(2);
    // مرتّبة تنازلياً بالإجمالي الشامل — الأب أولاً، والمعرّف منزوع الترميز.
    expect($facts['categories']['items'][0]['count'])->toBe(2);        // مباشر
    expect($facts['categories']['items'][0]['total_count'])->toBe(3);  // 2 + الطفل 1
    expect($facts['categories']['items'][0]['slug'])->not->toContain('%');

    // تحقّق الترميز العربي — لا UTF-8 غير صالح ولا mojibake في العيّنة.
    expect($facts['encoding']['invalid_utf8'])->toBe(0);
    expect($facts['encoding']['arabic_titles'])->toBeGreaterThan(0);
    expect($facts['encoding']['healthy'])->toBeTrue();

    expect($facts['seo']['provider'])->toBe('yoast');
    expect($facts['seo']['focus_keywords'])->toBe(1);

    expect($facts['authors']['guest_author_meta'])->toBe(1);
    expect($facts['authors']['wp_users'])->toBe(1);

    expect($facts['content']['gutenberg'])->toBe(1);
    expect($facts['content']['with_inline_images'])->toBe(1);
});

// ─── Endpoints: RBAC + validation ─────────────────────────────────────────────

it('requires wp-migration.manage to test a connection', function (): void {
    $token = wmToken('editor'); // admin role, but no wp-migration permission

    $this->withToken($token)
        ->postJson('/api/v1/admin/wp-migration/connection/test', [
            'db_host' => '127.0.0.1', 'db_name' => 'shaab', 'db_username' => 'root',
        ])
        ->assertStatus(403);
});

it('validates connection input (422 on missing fields)', function (): void {
    $token = wmToken('super_admin');

    $this->withToken($token)
        ->postJson('/api/v1/admin/wp-migration/connection/test', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['db_host', 'db_name', 'db_username']);
});

it('lists runs for an authorized operator', function (): void {
    $token = wmToken('super_admin');

    $this->withToken($token)
        ->getJson('/api/v1/admin/wp-migration/runs')
        ->assertOk()
        ->assertJsonPath('data', []);
});
