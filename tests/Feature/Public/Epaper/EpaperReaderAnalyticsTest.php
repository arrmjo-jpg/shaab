<?php

declare(strict_types=1);

use App\Models\Epaper;
use App\Models\EpaperIssueStat;
use App\Models\EpaperPageView;
use App\Models\EpaperSearchTerm;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use App\Settings\NewspaperSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    $s = app(NewspaperSettings::class);
    $s->enabled = true;
    $s->save();
});

function eranAsset(): MediaAsset
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

function eranIssue(array $attrs = []): Epaper
{
    static $n = 0;
    $n++;

    return Epaper::create(array_merge([
        'locale' => 'ar',
        'issue_number' => 950 + $n,
        'title' => 'عدد تحليلات '.$n,
        'slug' => 'analytics-'.$n,
        'publication_date' => now()->subDay()->toDateString(),
        'status' => 'published',
        'published_at' => now()->subDay(),
        'access_level' => 'public',
        'media_asset_id' => eranAsset()->id,
    ], $attrs));
}

function eranAdminToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function eranNoPermToken(): string
{
    Role::findByName('editor', 'web'); // موجود من seedRoles (بلا صلاحيات جريدة)
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

// ─── Ingestion → queued aggregation (queue is sync in tests) ─────────────────

it('records a reading session into aggregate stats (privacy-conscious, no PII)', function (): void {
    $e = eranIssue();

    // زائر مجهول يتتبّع (لا مصادقة مطلوبة) — يبرهن طبيعة التتبّع المجهول.
    $this->postJson($e->canonicalPath().'/track', [
        'duration' => 95,
        'pages' => [1, 2, 2, 5],
        'searches' => ['اقتصاد', 'رياضة'],
        'bookmarks_used' => 2,
        'resumed' => true,
    ])->assertOk()->assertJsonPath('accepted', true);

    $stat = EpaperIssueStat::where('epaper_id', $e->id)->firstOrFail();
    expect($stat->opens)->toBe(1);
    expect($stat->sessions)->toBe(1);
    expect($stat->total_duration_seconds)->toBe(95);
    expect($stat->pages_viewed)->toBe(3);   // الصفحات الفريدة: 1،2،5
    expect($stat->searches)->toBe(2);
    expect($stat->bookmarks_used)->toBe(2);
    expect($stat->resumes_used)->toBe(1);

    expect(EpaperPageView::where('epaper_id', $e->id)->where('page_number', 2)->value('views'))->toBe(1);
    expect(EpaperSearchTerm::where('epaper_id', $e->id)->where('term', 'اقتصاد')->value('count'))->toBe(1);
});

it('accumulates across multiple sessions', function (): void {
    $e = eranIssue();

    $this->postJson($e->canonicalPath().'/track', ['duration' => 10, 'pages' => [1], 'searches' => ['xx']])->assertOk();
    $this->postJson($e->canonicalPath().'/track', ['duration' => 20, 'pages' => [1, 2], 'searches' => ['xx']])->assertOk();

    $stat = EpaperIssueStat::where('epaper_id', $e->id)->firstOrFail();
    expect($stat->sessions)->toBe(2);
    expect($stat->total_duration_seconds)->toBe(30);
    expect(EpaperPageView::where('epaper_id', $e->id)->where('page_number', 1)->value('views'))->toBe(2);
    expect(EpaperSearchTerm::where('epaper_id', $e->id)->where('term', 'xx')->value('count'))->toBe(2);
});

it('validates the track payload (duration required)', function (): void {
    $this->postJson(eranIssue()->canonicalPath().'/track', ['pages' => [1]])->assertStatus(422);
});

// ─── Access + module gating parity ───────────────────────────────────────────

it('403s track for a subscriber issue to a guest', function (): void {
    $this->postJson(eranIssue(['access_level' => 'subscriber'])->canonicalPath().'/track', ['duration' => 5])
        ->assertStatus(403);
});

it('404s track for a private issue to a guest', function (): void {
    $this->postJson(eranIssue(['access_level' => 'private'])->canonicalPath().'/track', ['duration' => 5])
        ->assertNotFound();
});

it('404s track when the module is disabled', function (): void {
    $s = app(NewspaperSettings::class);
    $s->enabled = false;
    $s->save();

    $this->postJson(eranIssue()->canonicalPath().'/track', ['duration' => 5])->assertNotFound();
});

// ─── Admin reporting (basic) ─────────────────────────────────────────────────

it('returns basic reader analytics for an admin', function (): void {
    $e = eranIssue();
    $this->postJson($e->canonicalPath().'/track', [
        'duration' => 60, 'pages' => [1, 2, 3], 'searches' => ['اقتصاد'], 'bookmarks_used' => 1, 'resumed' => true,
    ])->assertOk();

    $res = $this->withToken(eranAdminToken())->getJson("/api/v1/admin/epapers/{$e->id}/analytics")->assertOk();

    expect($res->json('data.totals.sessions'))->toBe(1);
    expect($res->json('data.totals.avg_session_seconds'))->toBe(60);
    expect($res->json('data.totals.resumes_used'))->toBe(1);
    expect($res->json('data.top_pages'))->toHaveCount(3);
    expect($res->json('data.top_terms.0.term'))->toBe('اقتصاد');
});

it('requires epapers.view permission for analytics', function (): void {
    $this->withToken(eranNoPermToken())->getJson('/api/v1/admin/epapers/'.eranIssue()->id.'/analytics')
        ->assertStatus(403);
});

it('404s admin analytics when the module is disabled', function (): void {
    $e = eranIssue();
    $s = app(NewspaperSettings::class);
    $s->enabled = false;
    $s->save();

    $this->withToken(eranAdminToken())->getJson("/api/v1/admin/epapers/{$e->id}/analytics")->assertNotFound();
});
