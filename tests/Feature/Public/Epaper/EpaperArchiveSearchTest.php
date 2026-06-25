<?php

declare(strict_types=1);

use App\Models\Epaper;
use App\Models\EpaperPage;
use App\Models\MediaAsset;
use App\Models\User;
use App\Settings\NewspaperSettings;
use App\Support\Epaper\EpaperArchiveSearch;
use App\Support\Epaper\EpaperSearchIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    $s = app(NewspaperSettings::class);
    $s->enabled = true;
    $s->save();
});

function epasAsset(): MediaAsset
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

function epasIssue(array $attrs = []): Epaper
{
    static $n = 0;
    $n++;

    return Epaper::create(array_merge([
        'locale' => 'ar',
        'issue_number' => 700 + $n,
        'title' => 'عدد أرشيف '.$n,
        'slug' => 'archive-'.$n,
        'publication_date' => now()->subDays($n)->toDateString(),
        'status' => 'published',
        'published_at' => now()->subDays($n),
        'access_level' => 'public',
        'text_layer' => 'present',
        'ocr_status' => 'done',
        'page_count' => 5,
        'media_asset_id' => epasAsset()->id,
    ], $attrs));
}

function epasPage(Epaper $e, int $n, string $text): EpaperPage
{
    return EpaperPage::create([
        'epaper_id' => $e->id,
        'page_number' => $n,
        'text' => $text,
        'source' => 'embedded',
        'has_text' => trim($text) !== '',
    ]);
}

function epasAdmin(): User
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u;
}

// ─── Cross-issue results: metadata + snippet + deep-link ─────────────────────

it('searches across all published issues and returns issue metadata + snippet + deep link', function (): void {
    $a = epasIssue(['title' => 'العدد الأقدم', 'publication_date' => '2024-01-01', 'published_at' => '2024-01-01 00:00:00']);
    epasPage($a, 1, 'لا شيء هنا');
    epasPage($a, 3, 'تقرير عن الاقتصاد المحلي والنمو');

    $b = epasIssue(['title' => 'العدد الأحدث', 'publication_date' => '2024-06-01', 'published_at' => '2024-06-01 00:00:00']);
    epasPage($b, 2, 'الاقتصاد العالمي في تحوّل');

    $res = $this->getJson('/ar/epaper/search?q=الاقتصاد')->assertOk();

    expect($res->json('data.total'))->toBe(2);
    // أحدثها نشراً أولاً (b نُشر بعد a).
    expect($res->json('data.results.0.id'))->toBe($b->id);
    expect($res->json('data.results.1.id'))->toBe($a->id);

    // العدد الأقدم: أوّل صفحة مطابِقة = 3 ⇒ رابط عميق إلى /p/3 مع ?q.
    $old = collect($res->json('data.results'))->firstWhere('id', $a->id);
    expect($old['match']['page'])->toBe(3);
    expect($old['match']['snippet'])->toContain('الاقتصاد');
    expect($old['issue_number'])->toBe($a->issue_number);
    expect($old['title'])->toBe('العدد الأقدم');
    expect($old['url'])->toContain('/p/3');
    expect($old['url'])->toContain('q=');
});

it('returns one result per issue with a pages_matched count (no flooding)', function (): void {
    $e = epasIssue();
    epasPage($e, 1, 'الرياضة موضوع الصفحة الأولى');
    epasPage($e, 2, 'الرياضة مجدداً في الثانية');
    epasPage($e, 4, 'الرياضة للمرة الثالثة');

    $res = $this->getJson('/ar/epaper/search?q=الرياضة')->assertOk();

    expect($res->json('data.total'))->toBe(1);
    expect($res->json('data.results'))->toHaveCount(1);
    expect($res->json('data.results.0.match.pages_matched'))->toBe(3);
    expect($res->json('data.results.0.match.page'))->toBe(1); // أوّل صفحة مطابِقة
});

it('omits the /p segment when the first match is on page 1', function (): void {
    $e = epasIssue();
    epasPage($e, 1, 'كلمة افتتاحية في الصفحة الأولى');

    $res = $this->getJson('/ar/epaper/search?q=افتتاحية')->assertOk();

    $url = $res->json('data.results.0.url');
    expect($url)->not->toContain('/p/');
    expect($url)->toContain('q=');
});

// ─── Filters: locale (path), issue_number, date range ────────────────────────

it('restricts results to the path locale', function (): void {
    $ar = epasIssue(['locale' => 'ar', 'slug' => 'ar-loc']);
    epasPage($ar, 1, 'كلمة عربية مشتركة');

    $en = epasIssue(['locale' => 'en', 'slug' => 'en-loc', 'title' => 'English issue']);
    epasPage($en, 1, 'كلمة عربية مشتركة'); // نفس النصّ لكن لغة العدد en

    expect($this->getJson('/ar/epaper/search?q=عربية')->assertOk()->json('data.total'))->toBe(1);
    expect($this->getJson('/en/epaper/search?q=عربية')->assertOk()->json('data.total'))->toBe(1);
    expect($this->getJson('/ar/epaper/search?q=عربية')->json('data.results.0.locale'))->toBe('ar');
});

it('filters by issue_number', function (): void {
    $a = epasIssue(['issue_number' => 4242]);
    epasPage($a, 1, 'مصطلح مشترك للبحث');
    $b = epasIssue(['issue_number' => 4343]);
    epasPage($b, 1, 'مصطلح مشترك للبحث');

    $res = $this->getJson('/ar/epaper/search?q=مصطلح&issue_number=4242')->assertOk();
    expect($res->json('data.total'))->toBe(1);
    expect($res->json('data.results.0.issue_number'))->toBe(4242);
});

it('filters by publication date range', function (): void {
    $old = epasIssue(['publication_date' => '2020-01-01', 'published_at' => '2020-01-01 00:00:00']);
    epasPage($old, 1, 'حدث تاريخيّ قديم');
    $recent = epasIssue(['publication_date' => '2026-01-01', 'published_at' => '2026-01-01 00:00:00']);
    epasPage($recent, 1, 'حدث تاريخيّ حديث');

    $res = $this->getJson('/ar/epaper/search?q=تاريخيّ&date_from=2025-01-01')->assertOk();
    expect($res->json('data.total'))->toBe(1);
    expect($res->json('data.results.0.id'))->toBe($recent->id);

    $res2 = $this->getJson('/ar/epaper/search?q=تاريخيّ&date_to=2021-01-01')->assertOk();
    expect($res2->json('data.total'))->toBe(1);
    expect($res2->json('data.results.0.id'))->toBe($old->id);
});

// ─── Access-awareness (level-granular policy probe) ──────────────────────────

it('excludes subscriber and private issues from guest archive search', function (): void {
    $pub = epasIssue(['access_level' => 'public']);
    epasPage($pub, 1, 'محتوى عامّ قابل للبحث');
    $sub = epasIssue(['access_level' => 'subscriber']);
    epasPage($sub, 1, 'محتوى عامّ قابل للبحث');
    $prv = epasIssue(['access_level' => 'private']);
    epasPage($prv, 1, 'محتوى عامّ قابل للبحث');

    $res = $this->getJson('/ar/epaper/search?q=قابل')->assertOk();
    expect($res->json('data.total'))->toBe(1);
    expect($res->json('data.results.0.id'))->toBe($pub->id);
});

it('lets an admin search across subscriber and private issues', function (): void {
    $pub = epasIssue(['access_level' => 'public']);
    epasPage($pub, 1, 'كلمة في كل المستويات');
    $sub = epasIssue(['access_level' => 'subscriber']);
    epasPage($sub, 1, 'كلمة في كل المستويات');
    $prv = epasIssue(['access_level' => 'private']);
    epasPage($prv, 1, 'كلمة في كل المستويات');

    $res = $this->actingAs(epasAdmin())->getJson('/ar/epaper/search?q=المستويات')->assertOk();
    expect($res->json('data.total'))->toBe(3);
});

// ─── Excludes unpublished + pages without a text layer ───────────────────────

it('excludes draft/unpublished issues', function (): void {
    $draft = epasIssue(['status' => 'draft', 'published_at' => null]);
    epasPage($draft, 1, 'مسودة غير منشورة');

    $this->getJson('/ar/epaper/search?q=مسودة')->assertOk()
        ->assertJsonPath('data.total', 0);
});

it('ignores pages flagged has_text=false', function (): void {
    $e = epasIssue();
    EpaperPage::create([
        'epaper_id' => $e->id, 'page_number' => 1,
        'text' => 'نصّ موجود لكن غير معلّم', 'source' => 'embedded', 'has_text' => false,
    ]);

    $this->getJson('/ar/epaper/search?q=موجود')->assertOk()
        ->assertJsonPath('data.total', 0);
});

// ─── Pagination (by issue) ───────────────────────────────────────────────────

it('paginates the archive by issue', function (): void {
    for ($i = 1; $i <= 25; $i++) {
        $e = epasIssue();
        epasPage($e, 1, "خبر مكرّر للبحث رقم {$i}");
    }

    $res = $this->getJson('/ar/epaper/search?q=مكرّر&per_page=10')->assertOk();
    expect($res->json('data.total'))->toBe(25);
    expect($res->json('data.results'))->toHaveCount(10);
    expect($res->json('meta.pagination.total_pages'))->toBe(3);
    expect($res->json('meta.pagination.per_page'))->toBe(10);
});

// ─── Module guard + validation + LIKE safety ─────────────────────────────────

it('404s archive search when the module is disabled', function (): void {
    $e = epasIssue();
    epasPage($e, 1, 'نصّ قابل للبحث');
    $s = app(NewspaperSettings::class);
    $s->enabled = false;
    $s->save();

    $this->getJson('/ar/epaper/search?q=قابل')->assertNotFound();
});

it('422s when q is missing or too short', function (): void {
    $this->getJson('/ar/epaper/search')->assertStatus(422);
    $this->getJson('/ar/epaper/search?q=a')->assertStatus(422);
});

it('422s when date_to precedes date_from', function (): void {
    $this->getJson('/ar/epaper/search?q=test&date_from=2026-01-01&date_to=2025-01-01')->assertStatus(422);
});

it('escapes LIKE wildcards in archive search', function (): void {
    $a = epasIssue();
    epasPage($a, 1, 'discount a% applied at checkout');
    $b = epasIssue();
    epasPage($b, 1, 'banana split is on the menu'); // فيه «a» لكن ليس «a%»

    $res = $this->getJson('/ar/epaper/search?q='.urlencode('a%'))->assertOk();
    expect($res->json('data.total'))->toBe(1);
    expect($res->json('data.results.0.id'))->toBe($a->id);
});

// ─── Engine path (Meilisearch) — pure response parsing (no live engine) ──────

it('maps a Meilisearch raw response into archive rows + pagination', function (): void {
    $raw = [
        'hits' => [
            [
                'epaper_id' => 7, 'issue_number' => 950, 'issue_title' => 'عدد اقتصادي',
                'issue_slug' => 'econ', 'locale' => 'ar', 'access_level' => 'public',
                'publication_date' => strtotime('2025-03-01 00:00:00'), 'page_count' => 12,
                'page_number' => 4, '_formatted' => ['text' => '…تقرير عن الاقتصاد المحلي…'],
            ],
            [
                'epaper_id' => 3, 'issue_number' => 900, 'issue_title' => 'عدد رياضي',
                'issue_slug' => 'sport', 'locale' => 'ar', 'access_level' => 'public',
                'publication_date' => strtotime('2025-02-01 00:00:00'), 'page_count' => 8,
                'page_number' => 1, '_formatted' => ['text' => '…الاقتصاد في الرياضة…'],
            ],
        ],
        'facetDistribution' => ['epaper_id' => ['7' => 3, '3' => 1]],
        'totalHits' => 2, 'page' => 1, 'totalPages' => 1, 'hitsPerPage' => 20,
    ];

    $out = EpaperArchiveSearch::parseEngineResponse($raw, 'الاقتصاد', 20, 1);

    expect($out['engine'])->toBe('meilisearch');
    expect($out['degraded'])->toBeFalse();
    expect($out['pagination']['total'])->toBe(2);
    expect($out['pagination']['total_pages'])->toBe(1);
    expect($out['results'])->toHaveCount(2);

    $first = $out['results'][0];
    expect($first['id'])->toBe(7);
    expect($first['issue_number'])->toBe(950);
    expect($first['title'])->toBe('عدد اقتصادي');
    expect($first['match']['page'])->toBe(4);
    expect($first['match']['pages_matched'])->toBe(3);          // من facetDistribution
    expect($first['match']['snippet'])->toContain('الاقتصاد');   // من _formatted.text
    expect($first['url'])->toContain('/ar/epaper/7-econ/p/4');  // تنقّل دقيق
    expect($first['url'])->toContain('q=');
    expect($first['publication_date'])->toBe('2025-03-01');

    // مطابقة على الصفحة 1 ⇒ لا مقطع /p/ في الرابط.
    expect($out['results'][1]['url'])->not->toContain('/p/');
});

it('defaults pages_matched to 1 when the engine facet is missing', function (): void {
    $raw = [
        'hits' => [[
            'epaper_id' => 5, 'issue_number' => 1, 'issue_title' => 'x', 'issue_slug' => 'x',
            'locale' => 'ar', 'access_level' => 'public', 'page_number' => 2, '_formatted' => ['text' => '…نص…'],
        ]],
        'facetDistribution' => ['epaper_id' => []],
        'totalHits' => 1, 'page' => 1, 'totalPages' => 1, 'hitsPerPage' => 20,
    ];

    $out = EpaperArchiveSearch::parseEngineResponse($raw, 'نص', 20, 1);
    expect($out['results'][0]['match']['pages_matched'])->toBe(1);
});

// ─── Indexing guard — never touches engine/queue when the driver isn't Meili ─

it('does not queue index sync when the search engine is disabled', function (): void {
    Queue::fake();

    expect(EpaperSearchIndexer::enabled())->toBeFalse();
    EpaperSearchIndexer::queueSync(12345);

    Queue::assertNothingPushed();
});
