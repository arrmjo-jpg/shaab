<?php

declare(strict_types=1);

use App\Models\Epaper;
use App\Models\EpaperPage;
use App\Models\MediaAsset;
use App\Models\User;
use App\Settings\NewspaperSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    $s = app(NewspaperSettings::class);
    $s->enabled = true;
    $s->save();
});

function esAsset(): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'external',
        'disk' => 'external',
        'path' => '',
        'filename' => '',
        'original_name' => 'issue.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 1024,
        'checksum' => hash('sha256', Str::random()),
        'provider' => 'external',
        'source_url' => 'https://cdn.allowed.test/issue.pdf',
        'visibility' => 'public',
    ]);
}

function esIssue(array $attrs = []): Epaper
{
    static $n = 0;
    $n++;

    return Epaper::create(array_merge([
        'locale' => 'ar',
        'issue_number' => 600 + $n,
        'title' => 'عدد بحث '.$n,
        'slug' => 'search-'.$n,
        'publication_date' => now()->subDay()->toDateString(),
        'status' => 'published',
        'published_at' => now()->subDay(),
        'access_level' => 'public',
        'text_layer' => 'present',
        'ocr_status' => 'done',
        'page_count' => 3,
        'media_asset_id' => esAsset()->id,
    ], $attrs));
}

function esPage(Epaper $e, int $n, string $text): EpaperPage
{
    return EpaperPage::create([
        'epaper_id' => $e->id,
        'page_number' => $n,
        'text' => $text,
        'source' => 'embedded',
        'has_text' => trim($text) !== '',
    ]);
}

function esAdmin(): User
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u;
}

// ─── Core query + snippet + match metadata ───────────────────────────────────

it('returns matching pages with snippet and match count', function (): void {
    $e = esIssue();
    esPage($e, 1, 'الاقتصاد الوطني ينمو بقوة هذا العام');
    esPage($e, 2, 'الرياضة المحلية تشهد منافسة قوية');
    esPage($e, 3, 'تقرير عن الاقتصاد العالمي والاقتصاد المحلي');

    $res = $this->getJson($e->canonicalPath().'/search?q=الاقتصاد')->assertOk();

    expect($res->json('data.searchable'))->toBeTrue();
    expect($res->json('data.total'))->toBe(2);
    expect(collect($res->json('data.results'))->pluck('page')->all())->toBe([1, 3]);
    expect($res->json('data.results.0.matches'))->toBe(1);
    expect($res->json('data.results.1.matches'))->toBe(2); // «الاقتصاد» + «والاقتصاد»
    expect($res->json('data.results.0.snippet'))->toContain('الاقتصاد');
});

it('returns empty results (still searchable) when nothing matches', function (): void {
    $e = esIssue();
    esPage($e, 1, 'نصّ لا يحوي الكلمة المطلوبة');

    $res = $this->getJson($e->canonicalPath().'/search?q=غائبة')->assertOk();

    expect($res->json('data.searchable'))->toBeTrue();
    expect($res->json('data.total'))->toBe(0);
    expect($res->json('data.results'))->toBe([]);
});

it('treats a partial text layer as searchable', function (): void {
    $e = esIssue(['text_layer' => 'partial', 'ocr_status' => 'partial']);
    esPage($e, 1, 'كلمة مطلوبة في الصفحة الأولى');

    $res = $this->getJson($e->canonicalPath().'/search?q=مطلوبة')->assertOk();

    expect($res->json('data.searchable'))->toBeTrue();
    expect($res->json('data.total'))->toBe(1);
});

// ─── Graceful handling when OCR is unavailable ───────────────────────────────

it('gracefully reports not-searchable when OCR has not produced a text layer', function (): void {
    $e = esIssue(['text_layer' => null, 'ocr_status' => 'pending']);
    esPage($e, 1, 'نصّ موجود لكن الطبقة غير معلّمة'); // يُتجاهَل: الطبقة غير حاضرة

    $res = $this->getJson($e->canonicalPath().'/search?q=نصّ')->assertOk();

    expect($res->json('data.searchable'))->toBeFalse();
    expect($res->json('data.total'))->toBe(0);
    expect($res->json('data.results'))->toBe([]);
});

// ─── Access control (EpaperAccessPolicy) ─────────────────────────────────────

it('403s search for a subscriber issue to a guest', function (): void {
    $e = esIssue(['access_level' => 'subscriber']);
    esPage($e, 1, 'محتوى للمشتركين');

    $this->getJson($e->canonicalPath().'/search?q=محتوى')->assertStatus(403);
});

it('404s search for a private issue to a guest', function (): void {
    $e = esIssue(['access_level' => 'private']);
    esPage($e, 1, 'محتوى خاصّ');

    $this->getJson($e->canonicalPath().'/search?q=محتوى')->assertNotFound();
});

it('lets an admin search a subscriber issue', function (): void {
    $e = esIssue(['access_level' => 'subscriber']);
    esPage($e, 1, 'محتوى للمشتركين متاح للإدارة');

    $this->actingAs(esAdmin())->getJson($e->canonicalPath().'/search?q=محتوى')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

// ─── Resolution + module guard ───────────────────────────────────────────────

it('404s search for an unpublished issue', function (): void {
    $e = esIssue(['status' => 'draft', 'published_at' => null]);
    esPage($e, 1, 'نصّ');

    $this->getJson($e->canonicalPath().'/search?q=نصّ')->assertNotFound();
});

it('404s search when the module is disabled', function (): void {
    $s = app(NewspaperSettings::class);
    $s->enabled = false;
    $s->save();
    $e = esIssue();
    esPage($e, 1, 'نصّ قابل للبحث');

    $this->getJson($e->canonicalPath().'/search?q=قابل')->assertNotFound();
});

// ─── Validation ──────────────────────────────────────────────────────────────

it('422s when q is missing', function (): void {
    $e = esIssue();

    $this->getJson($e->canonicalPath().'/search')->assertStatus(422);
});

it('422s when q is too short', function (): void {
    $e = esIssue();

    $this->getJson($e->canonicalPath().'/search?q=a')->assertStatus(422);
});

// ─── Pagination ──────────────────────────────────────────────────────────────

it('paginates results within a single issue', function (): void {
    $e = esIssue();
    for ($i = 1; $i <= 25; $i++) {
        esPage($e, $i, "خبر رقم {$i} في هذه الصفحة");
    }

    $res = $this->getJson($e->canonicalPath().'/search?q=خبر&per_page=10')->assertOk();

    expect($res->json('data.total'))->toBe(25);
    expect($res->json('data.results'))->toHaveCount(10);
    expect($res->json('meta.pagination.per_page'))->toBe(10);
    expect($res->json('meta.pagination.total_pages'))->toBe(3);
    expect($res->json('meta.pagination.current_page'))->toBe(1);
});

// ─── LIKE wildcard safety ────────────────────────────────────────────────────

it('escapes LIKE wildcards so the query is matched literally', function (): void {
    $e = esIssue();
    esPage($e, 1, 'discount a% applied at checkout');
    esPage($e, 2, 'banana split on the menu'); // فيه «a» لكن ليس «a%»

    $res = $this->getJson($e->canonicalPath().'/search?q='.urlencode('a%'))->assertOk();

    // بلا تهريب لتحوّل LIKE %a%% لمطابقة أيّ نصّ فيه «a» (صفحتان)؛ مع التهريب: صفحة واحدة.
    expect($res->json('data.total'))->toBe(1);
    expect($res->json('data.results.0.page'))->toBe(1);
});
