<?php

declare(strict_types=1);

use App\Enums\EpaperAccessLevel;
use App\Models\Epaper;
use App\Models\MediaAsset;
use App\Models\User;
use App\Settings\NewspaperSettings;
use App\Support\Epaper\EpaperAccessPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function eaEnableModule(): void
{
    $s = app(NewspaperSettings::class);
    $s->enabled = true;
    $s->save();
}

function eaAsset(): MediaAsset
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

function eaIssue(array $attrs = []): Epaper
{
    static $n = 0;
    $n++;

    return Epaper::create(array_merge([
        'locale' => 'ar',
        'issue_number' => 700 + $n,
        'title' => 'عدد '.$n,
        'slug' => 'access-'.$n,
        'publication_date' => now()->subDay()->toDateString(),
        'status' => 'published',
        'published_at' => now()->subDay(),
        'media_asset_id' => eaAsset()->id,
    ], $attrs));
}

function eaAdmin(): User
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u;
}

// ─── Domain ────────────────────────────────────────────────────────────────

it('defaults access_level to public and casts it to the enum', function (): void {
    $e = eaIssue();
    expect($e->access_level)->toBe(EpaperAccessLevel::Public);
    expect($e->fresh()->access_level)->toBe(EpaperAccessLevel::Public);
});

it('audits an access_level change on the issue (event row recorded)', function (): void {
    $e = eaIssue();
    $e->update(['access_level' => EpaperAccessLevel::Subscriber->value]);

    // access_level مُدرَج في $auditAttributes ⇒ التغيير يُنشئ صفّ نشاط (event=updated)
    // على العدد. (ملاحظة: محتوى properties فارغ عبر المنصّة كلها في AuditsChanges —
    // مسألة منهجية سابقة مُبلَّغة، خارج نطاق 3أ.)
    $logged = Activity::query()
        ->where('log_name', 'epaper')
        ->where('event', 'updated')
        ->where('subject_type', Epaper::class)
        ->where('subject_id', $e->id)
        ->exists();

    expect($logged)->toBeTrue();
});

// ─── Gating (public route) ───────────────────────────────────────────────────

it('renders the reader for a public issue to a guest', function (): void {
    eaEnableModule();
    $e = eaIssue(['access_level' => 'public', 'slug' => 'pub']);

    $this->get($e->canonicalPath())->assertOk()->assertSee('data-epaper-reader', false);
});

it('shows a teaser/paywall (200) for a subscriber issue to a guest — no reader, no pdf', function (): void {
    eaEnableModule();
    $e = eaIssue(['access_level' => 'subscriber', 'slug' => 'sub', 'title' => 'عدد المشتركين', 'summary' => 'ملخّص العدد']);

    $html = $this->get($e->canonicalPath())->assertOk()->getContent();

    expect($html)->toContain('عدد المشتركين');                 // title exposed
    expect($html)->toContain('ملخّص العدد');                    // summary exposed
    expect($html)->toContain('هذا العدد متاح للمشتركين فقط');   // subscriber_only notice
    expect($html)->not->toContain('data-epaper-reader');        // NO reader mount
    expect($html)->not->toContain('https://cdn.allowed.test/issue.pdf'); // NO pdf delivery
});

it('404s a private issue for a guest', function (): void {
    eaEnableModule();
    $e = eaIssue(['access_level' => 'private', 'slug' => 'priv']);

    $this->get($e->canonicalPath())->assertNotFound();
});

it('lets an admin view a subscriber issue (reader renders)', function (): void {
    eaEnableModule();
    $e = eaIssue(['access_level' => 'subscriber', 'slug' => 'sub-admin']);

    $this->actingAs(eaAdmin())->get($e->canonicalPath())->assertOk()->assertSee('data-epaper-reader', false);
});

it('lets an admin view a private issue (reader renders)', function (): void {
    eaEnableModule();
    $e = eaIssue(['access_level' => 'private', 'slug' => 'priv-admin']);

    $this->actingAs(eaAdmin())->get($e->canonicalPath())->assertOk()->assertSee('data-epaper-reader', false);
});

it('excludes private from the public archive but includes subscriber + public', function (): void {
    eaEnableModule();
    eaIssue(['access_level' => 'public', 'slug' => 'arch-pub', 'title' => 'عام بالأرشيف']);
    eaIssue(['access_level' => 'subscriber', 'slug' => 'arch-sub', 'title' => 'مشترك بالأرشيف']);
    eaIssue(['access_level' => 'private', 'slug' => 'arch-priv', 'title' => 'خاص بالأرشيف']);

    $html = $this->get('/ar/epaper')->assertOk()->getContent();

    expect($html)->toContain('عام بالأرشيف');
    expect($html)->toContain('مشترك بالأرشيف');
    expect($html)->not->toContain('خاص بالأرشيف');
});

// ─── Default policy semantics ────────────────────────────────────────────────

it('applies the conservative default policy (no faked subscriber support)', function (): void {
    $policy = app(EpaperAccessPolicy::class);

    expect($policy->canView(null, eaIssue(['access_level' => 'public'])))->toBeTrue();        // guest sees public
    expect($policy->canView(null, eaIssue(['access_level' => 'subscriber'])))->toBeFalse();   // subscriber denied by default
    expect($policy->canView(null, eaIssue(['access_level' => 'private'])))->toBeFalse();      // private denied
    expect($policy->canView(eaAdmin(), eaIssue(['access_level' => 'private'])))->toBeTrue();  // admin unrestricted

    expect($policy->canDownload(null, eaIssue(['access_level' => 'public'])))->toBeFalse();   // download is entitled
    expect($policy->canDownload(eaAdmin(), eaIssue(['access_level' => 'public'])))->toBeTrue();
});

it('shows the subscribe CTA on the paywall when subscribe_url is configured', function (): void {
    $s = app(NewspaperSettings::class);
    $s->enabled = true;
    $s->subscribe_url = 'https://sub.test/join';
    $s->save();
    $e = eaIssue(['access_level' => 'subscriber', 'slug' => 'cta']);

    $html = $this->get($e->canonicalPath())->assertOk()->getContent();

    expect($html)->toContain('https://sub.test/join');
});
