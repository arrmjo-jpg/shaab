<?php

declare(strict_types=1);

use App\Actions\Admin\Vertix\ImportVertixCategoriesAction;
use App\Actions\Admin\Vertix\ImportVertixNewsBatchAction;
use App\Enums\VertixPhase;
use App\Jobs\GenerateMediaAssetConversionsJob;
use App\Models\Article;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\VertixRun;
use App\Support\Vertix\VertixConnection;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** صفّ أخبار كامل الأعمدة (sqlite يطلب تطابق الأعمدة في الإدراج الجَماعيّ). */
function vxNews(array $o): array
{
    return array_merge([
        'newsid' => 0, 'catid' => 2, 'title' => '', 'link' => null, 'brief' => null, 'body' => 'x',
        'keywords' => null, 'ph_name' => null, 'folder' => null, 'createdate' => null,
        'updatedate_int' => null, 'lang' => 'arb', 'views' => 0, 'status' => 1, 'deleteflag' => 0,
    ], $o);
}

/** مصدر Vertix مزيّف (sqlite) — قسمان + 4 أخبار (٢ مؤهَّلان). */
function vertixFakeSource(): Connection
{
    config(['database.connections.vertix_fake' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]]);
    DB::purge('vertix_fake');
    $c = DB::connection('vertix_fake');
    $s = $c->getSchemaBuilder();

    $s->create('art_categories', function (Blueprint $t): void {
        $t->integer('catid');
        $t->integer('parentid')->nullable();
        $t->string('title')->nullable();
        $t->string('seo_name')->nullable();
        $t->string('lang')->nullable();
        $t->integer('status')->nullable();
    });
    $s->create('art_news', function (Blueprint $t): void {
        $t->integer('newsid');
        $t->integer('catid')->nullable();
        $t->string('title')->nullable();
        $t->text('link')->nullable();
        $t->text('brief')->nullable();
        $t->longText('body')->nullable();
        $t->string('keywords')->nullable();
        $t->string('ph_name')->nullable();
        $t->string('folder')->nullable();
        $t->string('createdate')->nullable();
        $t->integer('updatedate_int')->nullable();
        $t->string('lang')->nullable();
        $t->integer('views')->nullable();
        $t->integer('status')->nullable();
        $t->integer('deleteflag')->nullable();
    });

    $c->table('art_categories')->insert([
        ['catid' => 2, 'parentid' => 0, 'title' => 'محلية', 'seo_name' => 'mahaliya', 'lang' => 'arb', 'status' => 1],
        ['catid' => 9, 'parentid' => 0, 'title' => 'رياضة', 'seo_name' => 'riyada', 'lang' => 'arb', 'status' => 1],
    ]);
    $c->table('art_news')->insert([
        vxNews(['newsid' => 1000, 'catid' => 2, 'title' => 'خبر أول', 'link' => 'khabar-awwal', 'brief' => 'موجز صريح', 'body' => '<h2>عنوان فرعي</h2><p>نصّ فيه <a href="https://example.com/x">رابط</a> مهمّ.</p><img src="https://cdn.alqalahnews.net/2024-11-10/images/inline-99.jpg" alt="داخليّة"><ul><li>أوّل</li><li>ثاني</li></ul><table><tr><th>ع</th><td>ق</td></tr></table><blockquote>اقتباس</blockquote>', 'ph_name' => '1000_1_1.jpeg', 'folder' => '2024-11-10', 'createdate' => '2024-11-10', 'updatedate_int' => 1731235558, 'views' => 5]),
        vxNews(['newsid' => 1001, 'catid' => 9, 'title' => 'خبر رياضي', 'link' => 'khabar-riyadi', 'body' => '<p>نص</p>', 'ph_name' => '1001_1_1.jpeg', 'folder' => '2024-11-11', 'updatedate_int' => 1731300000]),
        vxNews(['newsid' => 1002, 'title' => 'محذوف', 'deleteflag' => 1]),   // مستبعد
        vxNews(['newsid' => 1003, 'title' => 'غير منشور', 'status' => 0]),   // مستبعد
    ]);

    return $c;
}

/** PNG صالح 1×1 لتزييف استجابة الصورة (يُكتشَف image/png بـfinfo + getimagesize). */
function vxPng(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
}

/** هل يحتوي المستند عقدةً من نوع معيّن (بحث تعاوديّ)؟ */
function vxHasNode(array $doc, string $type): bool
{
    foreach ($doc['content'] ?? [] as $n) {
        if (! is_array($n)) {
            continue;
        }
        if (($n['type'] ?? null) === $type) {
            return true;
        }
        if (vxHasNode($n, $type)) {
            return true;
        }
    }

    return false;
}

/**
 * كلّ روابط صور المستند (src) تعاوديًّا.
 *
 * @return array<int,string>
 */
function vxImageSrcs(array $doc): array
{
    $out = [];
    foreach ($doc['content'] ?? [] as $n) {
        if (! is_array($n)) {
            continue;
        }
        if (($n['type'] ?? null) === 'image') {
            $out[] = (string) ($n['attrs']['src'] ?? '');
        }
        $out = array_merge($out, vxImageSrcs($n));
    }

    return $out;
}

beforeEach(function (): void {
    Storage::fake('uploads');
    Queue::fake();
    config(['media-library.disk_name' => 'uploads']);
    // صور Vertix تُنزَّل من CDN خارجيّ ⇒ نُزيّف استجابة الصورة (PNG صالح) لاختبار محكم بلا شبكة.
    Http::fake(['cdn.alqalahnews.net/*' => Http::response(vxPng(), 200, ['Content-Type' => 'image/png'])]);

    VertixConnection::fake(vertixFakeSource());
});

afterEach(function (): void {
    VertixConnection::forget();
});

it('imports categories preserving the original id (categories.id = catid), idempotent', function (): void {
    (new ImportVertixCategoriesAction)->handle();

    expect(Category::whereKey(2)->exists())->toBeTrue();   // المعرّف = catid بالضبط
    expect(Category::whereKey(9)->exists())->toBeTrue();
    expect(Category::find(2)->id)->toBe(2);
    expect(Category::count())->toBe(2);

    // إعادة التشغيل ⇒ لا تكرار (مطابقة بالـ id).
    (new ImportVertixCategoriesAction)->handle();
    expect(Category::count())->toBe(2);
});

it('imports news preserving the original id (articles.id = newsid), newest-first, idempotent', function (): void {
    (new ImportVertixCategoriesAction)->handle();

    $run = VertixRun::forPhase(VertixPhase::News);
    ImportVertixNewsBatchAction::initialize($run); // high_water=1001، cursor=1002
    $action = new ImportVertixNewsBatchAction;

    // الأحدث أولاً: دفعة بحجم 1 ⇒ تستورد 1001 (الأعلى) قبل 1000.
    $action->handleRun($run->refresh(), 1);
    expect(Article::whereKey(1001)->exists())->toBeTrue();
    expect(Article::whereKey(1000)->exists())->toBeFalse();

    // أكمل الباقي.
    do {
        $res = $action->handleRun($run->refresh(), 50);
    } while (! $res['done']);

    expect(Article::count())->toBe(2);
    $art = Article::find(1000);
    expect($art->id)->toBe(1000);                  // = newsid بالضبط، بلا تحويل
    expect($art->primary_category_id)->toBe(2);    // = catid مباشرةً
    expect($art->title)->toBe('خبر أول');
    // الغلاف: الصورة البارزة → MediaAsset (og_image_id + article_media cover) — منفصلة عن المتن.
    expect($art->og_image_id)->not->toBeNull();
    expect($art->mediaAssets()->wherePivot('collection', 'cover')->count())->toBe(1);
    expect($art->content)->not->toContain('1000_1_1.jpeg'); // البارزة ليست داخل المتن

    // ✅ صفر فقدان للمتن: صورة المتن (بمصدرها الأصليّ) + الرابط + العنوان + القائمة + الجدول + الاقتباس.
    expect(vxImageSrcs($art->content_json))->toContain('https://cdn.alqalahnews.net/2024-11-10/images/inline-99.jpg');
    expect(vxHasNode($art->content_json, 'heading'))->toBeTrue();
    expect(vxHasNode($art->content_json, 'bulletList'))->toBeTrue();
    expect(vxHasNode($art->content_json, 'table'))->toBeTrue();
    expect(vxHasNode($art->content_json, 'blockquote'))->toBeTrue();
    expect($art->content)->toContain('example.com/x'); // الرابط محفوظ

    // الصورة البارزة المُنزَّلة deduped لأصل واحد (نفس PNG للخبرين)؛ صور المتن لا تُنزَّل (تبقى بمصدرها).
    expect(MediaAsset::count())->toBe(1);
    expect(DB::table('article_media')->where('collection', 'cover')->count())->toBe(2);
    Queue::assertPushed(GenerateMediaAssetConversionsJob::class);

    // Idempotent: إعادة ⇒ لا جديد ولا تكرار (لا مقالات ولا أصول ولا أغلفة مكرّرة).
    $r = $action->handleRun($run->refresh(), 50);
    expect($r['done'])->toBeTrue();
    expect(Article::count())->toBe(2);
    expect(MediaAsset::count())->toBe(1);
    expect(DB::table('article_media')->where('collection', 'cover')->count())->toBe(2);
});

it('catches newly-added news above the high-water after backfill, preserving id', function (): void {
    (new ImportVertixCategoriesAction)->handle();

    $run = VertixRun::forPhase(VertixPhase::News);
    ImportVertixNewsBatchAction::initialize($run);
    $action = new ImportVertixNewsBatchAction;
    do {
        $res = $action->handleRun($run->refresh(), 100);
    } while (! $res['done']);
    expect(Article::count())->toBe(2);

    // أُضيف خبر جديد بمعرّف أعلى لاحقاً.
    VertixConnection::db()->table('art_news')->insert([
        vxNews(['newsid' => 2000, 'catid' => 9, 'title' => 'خبر جديد', 'link' => 'jadid', 'body' => '<p>جديد</p>', 'ph_name' => '2000.jpeg', 'folder' => '2025-01-01', 'updatedate_int' => 1735689600]),
    ]);

    $r = $action->handleRun($run->refresh(), 100);
    expect($r['imported'])->toBe(1);
    expect(Article::whereKey(2000)->exists())->toBeTrue(); // المعرّف الأصليّ محفوظ
    expect(Article::count())->toBe(3);
});

it('priority on resume: new news imported BEFORE continuing the older archive (stop mid-backfill)', function (): void {
    (new ImportVertixCategoriesAction)->handle();

    $run = VertixRun::forPhase(VertixPhase::News);
    ImportVertixNewsBatchAction::initialize($run); // high_water=1001، cursor=1002
    $action = new ImportVertixNewsBatchAction;

    // استورد الأحدث فقط (دفعة 1) ثمّ «أوقِف» — الأرشيف القديم لم يُستكمَل.
    $action->handleRun($run->refresh(), 1);
    expect(Article::whereKey(1001)->exists())->toBeTrue();   // الأحدث استُورِد أوّلاً
    expect(Article::whereKey(1000)->exists())->toBeFalse();  // الأقدم لم يصل بعد

    // أُضيف خبر جديد (معرّف أعلى) أثناء التوقّف.
    VertixConnection::db()->table('art_news')->insert([
        vxNews(['newsid' => 2000, 'catid' => 9, 'title' => 'جديد', 'body' => '<p>x</p>', 'ph_name' => '2000.jpeg', 'folder' => '2025-01-01', 'updatedate_int' => 1735689600]),
    ]);

    // إعادة التشغيل: الجديد (2000) أولاً — قبل استكمال الأرشيف القديم (1000).
    $r = $action->handleRun($run->refresh(), 1);
    expect($r['imported'])->toBe(1);
    expect(Article::whereKey(2000)->exists())->toBeTrue();   // ← الأولويّة 1: الجديد
    expect(Article::whereKey(1000)->exists())->toBeFalse();  // ← الأرشيف القديم لم يُستكمَل بعد

    // الدورة التالية: يُكمل الأرشيف القديم تنازلياً (1000).
    $action->handleRun($run->refresh(), 1);
    expect(Article::whereKey(1000)->exists())->toBeTrue();
    expect(Article::count())->toBe(3);
});

it('drops only the featured image when duplicated at the head of body; keeps other body images', function (): void {
    (new ImportVertixCategoriesAction)->handle();

    // خبر صورته البارزة (3000.jpg) تظهر أيضاً كأوّل صورة في صدر المتن + صورة متن أخرى (other.jpg).
    $featured = 'https://cdn.alqalahnews.net/2025-02-02/images/3000.jpg';
    $other = 'https://cdn.alqalahnews.net/2025-02-02/images/other.jpg';
    VertixConnection::db()->table('art_news')->insert([
        vxNews(['newsid' => 3000, 'catid' => 2, 'title' => 'تكرار البارزة', 'link' => 'dup',
            'body' => '<img src="'.$featured.'"><p>متن</p><img src="'.$other.'">',
            'ph_name' => '3000.jpg', 'folder' => '2025-02-02', 'updatedate_int' => 1738454400]),
    ]);

    $run = VertixRun::forPhase(VertixPhase::News);
    ImportVertixNewsBatchAction::initialize($run);
    $action = new ImportVertixNewsBatchAction;
    do {
        $res = $action->handleRun($run->refresh(), 100);
    } while (! $res['done']);

    $art = Article::find(3000);
    $srcs = vxImageSrcs($art->content_json);
    expect($art->og_image_id)->not->toBeNull();   // البارزة → غلاف منفصل
    expect($srcs)->not->toContain($featured);      // التكرار في صدر المتن أُزيل (هو نفسه الغلاف)
    expect($srcs)->toContain($other);              // بقيّة صور المتن باقية كما هي
});
