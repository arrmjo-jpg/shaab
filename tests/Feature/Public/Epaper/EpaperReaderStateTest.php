<?php

declare(strict_types=1);

use App\Models\Epaper;
use App\Models\EpaperBookmark;
use App\Models\EpaperReadingProgress;
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

function rsAsset(): MediaAsset
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

function rsIssue(array $attrs = []): Epaper
{
    static $n = 0;
    $n++;

    return Epaper::create(array_merge([
        'locale' => 'ar',
        'issue_number' => 900 + $n,
        'title' => 'عدد احتفاظ '.$n,
        'slug' => 'retain-'.$n,
        'publication_date' => now()->subDay()->toDateString(),
        'status' => 'published',
        'published_at' => now()->subDay(),
        'access_level' => 'public',
        'media_asset_id' => rsAsset()->id,
    ], $attrs));
}

function rsUser(): User
{
    return User::factory()->create(); // مستخدم عام بلا أدوار (ليس طاقماً)
}

function rsAdmin(): User
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u;
}

// ─── Auth boundary: guests use localStorage (401), authed use server ─────────

it('401s reader state for a guest on a public issue', function (): void {
    $this->getJson(rsIssue()->canonicalPath().'/state')->assertStatus(401);
});

it('returns empty state for a fresh authenticated user', function (): void {
    $res = $this->actingAs(rsUser())->getJson(rsIssue()->canonicalPath().'/state')->assertOk();

    expect($res->json('last_page'))->toBeNull();
    expect($res->json('bookmarks'))->toBe([]);
});

// ─── Resume reading ──────────────────────────────────────────────────────────

it('saves and resumes the last-read page (one row per user+issue)', function (): void {
    $u = rsUser();
    $e = rsIssue();

    $this->actingAs($u)->putJson($e->canonicalPath().'/progress', ['page' => 12])->assertOk();
    expect($this->actingAs($u)->getJson($e->canonicalPath().'/state')->json('last_page'))->toBe(12);

    $this->actingAs($u)->putJson($e->canonicalPath().'/progress', ['page' => 20])->assertOk();
    expect($this->actingAs($u)->getJson($e->canonicalPath().'/state')->json('last_page'))->toBe(20);

    expect(EpaperReadingProgress::where('user_id', $u->id)->where('epaper_id', $e->id)->count())->toBe(1);
});

it('validates the progress payload', function (): void {
    $this->actingAs(rsUser())->putJson(rsIssue()->canonicalPath().'/progress', [])->assertStatus(422);
    $this->actingAs(rsUser())->putJson(rsIssue()->canonicalPath().'/progress', ['page' => 0])->assertStatus(422);
});

// ─── Bookmarks ───────────────────────────────────────────────────────────────

it('adds, lists, and removes bookmarks', function (): void {
    $u = rsUser();
    $e = rsIssue();

    $this->actingAs($u)->postJson($e->canonicalPath().'/bookmarks', ['page' => 7])->assertOk();
    $this->actingAs($u)->postJson($e->canonicalPath().'/bookmarks', ['page' => 3])->assertOk();
    // adding the same page again is idempotent (no duplicate row)
    $this->actingAs($u)->postJson($e->canonicalPath().'/bookmarks', ['page' => 3])->assertOk();

    expect($this->actingAs($u)->getJson($e->canonicalPath().'/state')->json('bookmarks'))->toBe([3, 7]);
    expect(EpaperBookmark::where('user_id', $u->id)->where('epaper_id', $e->id)->count())->toBe(2);

    $this->actingAs($u)->deleteJson($e->canonicalPath().'/bookmarks/3')->assertOk();
    expect($this->actingAs($u)->getJson($e->canonicalPath().'/state')->json('bookmarks'))->toBe([7]);
});

it('scopes bookmarks per user', function (): void {
    $e = rsIssue();
    $a = rsUser();
    $b = rsUser();

    $this->actingAs($a)->postJson($e->canonicalPath().'/bookmarks', ['page' => 5])->assertOk();

    expect($this->actingAs($b)->getJson($e->canonicalPath().'/state')->json('bookmarks'))->toBe([]);
});

// ─── Access control parity with delivery (canView) ──────────────────────────

it('403s state for a subscriber issue to a non-staff authenticated user', function (): void {
    $e = rsIssue(['access_level' => 'subscriber']);

    $this->actingAs(rsUser())->getJson($e->canonicalPath().'/state')->assertStatus(403);
});

it('404s state for a private issue to a guest (no existence leak)', function (): void {
    $e = rsIssue(['access_level' => 'private']);

    $this->getJson($e->canonicalPath().'/state')->assertNotFound();
});

it('lets an admin use reader state on a subscriber issue', function (): void {
    $e = rsIssue(['access_level' => 'subscriber']);
    $admin = rsAdmin();

    $this->actingAs($admin)->putJson($e->canonicalPath().'/progress', ['page' => 4])->assertOk();
    expect($this->actingAs($admin)->getJson($e->canonicalPath().'/state')->json('last_page'))->toBe(4);
});

// ─── Resolution + module guard ───────────────────────────────────────────────

it('404s state for an unpublished issue', function (): void {
    $e = rsIssue(['status' => 'draft', 'published_at' => null]);

    $this->actingAs(rsUser())->getJson($e->canonicalPath().'/state')->assertNotFound();
});

it('404s state when the module is disabled', function (): void {
    $s = app(NewspaperSettings::class);
    $s->enabled = false;
    $s->save();

    $this->actingAs(rsUser())->getJson(rsIssue()->canonicalPath().'/state')->assertNotFound();
});
