<?php

declare(strict_types=1);

use App\Models\Epaper;
use App\Models\MediaAsset;
use App\Settings\NewspaperSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function epEnableModule(): void
{
    $s = app(NewspaperSettings::class);
    $s->enabled = true;
    $s->save();
}

/** أصل PDF خارجيّ — url() يرجع source_url بلا قرص (بذرة التسليم في 2أ). */
function epPdfAsset(): MediaAsset
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

/** عدد منشور بـ slug ASCII صريح (تفادي حواف توجيه اليونيكود في الاختبار). */
function epPublishedIssue(array $attrs = []): Epaper
{
    static $n = 0;
    $n++;

    return Epaper::create(array_merge([
        'locale' => 'ar',
        'issue_number' => 500 + $n,
        'title' => 'عدد منشور '.$n,
        'slug' => 'issue-'.$n,
        'publication_date' => now()->subDay()->toDateString(),
        'status' => 'published',
        'published_at' => now()->subDay(),
        'media_asset_id' => epPdfAsset()->id,
    ], $attrs));
}

// ─── Archive ─────────────────────────────────────────────────────────────────

it('lists only published issues of the locale on the archive', function (): void {
    epEnableModule();
    epPublishedIssue(['title' => 'العدد المنشور', 'slug' => 'live-issue']);
    epPublishedIssue(['title' => 'مسودة مخفية', 'slug' => 'hidden-draft', 'status' => 'draft', 'published_at' => null]);
    epPublishedIssue(['locale' => 'en', 'title' => 'EN Issue', 'slug' => 'en-issue']);

    $html = $this->get('/ar/epaper')->assertOk()->getContent();

    expect($html)->toContain('العدد المنشور');
    expect($html)->not->toContain('مسودة مخفية');
    expect($html)->not->toContain('EN Issue');
});

// ─── Reader (show) ─────────────────────────────────────────────────────────

it('renders the reader shell with the pdf delivery seam for a published issue', function (): void {
    epEnableModule();
    $issue = epPublishedIssue(['title' => 'عدد القراءة', 'slug' => 'read-me']);

    $html = $this->get($issue->canonicalPath())->assertOk()->getContent();

    expect($html)->toContain('عدد القراءة');
    expect($html)->toContain('data-epaper-reader');
    expect($html)->toContain('data-doc-endpoint');                    // ← access-checked mint endpoint (no raw URL)
    expect($html)->not->toContain('https://cdn.allowed.test/issue.pdf'); // raw PDF URL never leaked to the page
    expect($html)->toContain('data-epaper-i18n');                     // reader labels delivered to JS
    expect($html)->toContain('ملاءمة العرض');                          // localized (ar) fit-width label
});

it('404s an unpublished issue', function (): void {
    epEnableModule();
    $draft = epPublishedIssue(['slug' => 'draft-x', 'status' => 'draft', 'published_at' => null]);

    $this->get($draft->canonicalPath())->assertNotFound();
});

it('redirects a wrong slug to the canonical issue url (301)', function (): void {
    epEnableModule();
    $issue = epPublishedIssue(['slug' => 'correct-slug']);

    $this->get("/ar/epaper/{$issue->id}-wrong-slug")
        ->assertStatus(301)
        ->assertRedirect($issue->canonicalPath());
});

it('accepts a path-based page deep-link and passes the initial page to the reader', function (): void {
    epEnableModule();
    $issue = epPublishedIssue(['slug' => 'paged']);

    $html = $this->get($issue->canonicalPath().'/p/3')->assertOk()->getContent();

    expect($html)->toContain('data-initial-page="3"');
});

// ─── Module gate + locale constraint ─────────────────────────────────────────

it('404s the public archive and reader when the module is disabled', function (): void {
    // الوحدة معطَّلة افتراضياً — لا تفعيل.
    $issue = epPublishedIssue(['slug' => 'gated']);

    $this->get('/ar/epaper')->assertNotFound();
    $this->get($issue->canonicalPath())->assertNotFound();
});

it('does not match the reader routes for an unsupported locale', function (): void {
    epEnableModule();

    $this->get('/fr/epaper')->assertNotFound();
});
