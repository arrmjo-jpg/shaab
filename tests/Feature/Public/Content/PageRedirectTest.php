<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\PageUrlHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Cache::flush();
});

/** صفحة منشورة (published_at مُضبط) — كي ينطبق التقاط التاريخ على تغيير الـ slug. */
function rdPage(string $slug, string $locale = 'ar', array $attrs = []): Page
{
    return Page::create(array_merge([
        'title' => 'title-'.uniqid(),
        'slug' => $slug,
        'locale' => $locale,
        'status' => 'published',
        'content' => '<p>x</p>',
        'published_at' => now()->subDay(),
    ], $attrs))->fresh();
}

function rdPageAdminToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── 1) Slug change → captured + 301 on the public page endpoint ────────────

it('captures old canonical and 301-redirects to the new slug after a slug change', function (): void {
    [, $token] = rdPageAdminToken();
    $page = rdPage('about-old');

    // تغيير الـ slug عبر مسار الـ Update الرسميّ (يلتقط التاريخ).
    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", [
        'slug' => 'about-new',
    ])->assertOk();

    expect(PageUrlHistory::where('old_path', '/ar/pages/about-old')->exists())->toBeTrue();

    // الطلب على الـ slug القديم يعيد 301 إلى الجديد.
    $res = $this->getJson('/api/v1/ar/pages/about-old');
    $res->assertStatus(301);
    expect($res->headers->get('Location'))->toEndWith('/api/v1/ar/pages/about-new');

    // والـ slug الجديد يُخدَم كالمعتاد (200) — لا حلقة.
    $this->getJson('/api/v1/ar/pages/about-new')->assertOk();
});

// ─── 2) Multiple historical slug changes — أعمدة متعدّدة كلها تُعيد للحالي ──

it('301-redirects every historical slug to the current one through multiple renames', function (): void {
    [, $token] = rdPageAdminToken();
    $page = rdPage('v1');

    // سلسلة إعادات تسمية: v1 → v2 → v3
    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", ['slug' => 'v2'])->assertOk();
    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", ['slug' => 'v3'])->assertOk();

    expect(PageUrlHistory::where('page_id', $page->id)->count())->toBe(2);
    expect(PageUrlHistory::where('old_path', '/ar/pages/v1')->exists())->toBeTrue();
    expect(PageUrlHistory::where('old_path', '/ar/pages/v2')->exists())->toBeTrue();

    // كلا الـ slugs القديمَين يعيدان 301 إلى v3.
    $r1 = $this->getJson('/api/v1/ar/pages/v1')->assertStatus(301);
    expect($r1->headers->get('Location'))->toEndWith('/api/v1/ar/pages/v3');

    $r2 = $this->getJson('/api/v1/ar/pages/v2')->assertStatus(301);
    expect($r2->headers->get('Location'))->toEndWith('/api/v1/ar/pages/v3');
});

// ─── 3) No duplicate history entries — القيد الفريد + firstOrCreate ────────

it('does not create duplicate history rows when reusing an old slug back-and-forth', function (): void {
    [, $token] = rdPageAdminToken();
    $page = rdPage('foo');

    // foo → bar (يلتقط /ar/pages/foo)
    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", ['slug' => 'bar'])->assertOk();
    // bar → foo (يلتقط /ar/pages/bar). يعود canonical إلى /ar/pages/foo.
    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", ['slug' => 'foo'])->assertOk();
    // foo → bar مجدّداً → /ar/pages/foo موجود مسبقاً في التاريخ ⇒ firstOrCreate لا يكرّر.
    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", ['slug' => 'bar'])->assertOk();

    // مدخلات تاريخ فريدة لكل (locale, old_path): foo و bar — لا تكرار.
    expect(PageUrlHistory::where('page_id', $page->id)->count())->toBe(2);
    expect(PageUrlHistory::where('locale', 'ar')->where('old_path', '/ar/pages/foo')->count())->toBe(1);
    expect(PageUrlHistory::where('locale', 'ar')->where('old_path', '/ar/pages/bar')->count())->toBe(1);
});

// ─── 4) Dedicated /redirects/pages endpoint — old full path resolution ─────

it('resolves a full old canonical path to a 301 via /redirects/pages', function (): void {
    [, $token] = rdPageAdminToken();
    $page = rdPage('original');
    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", ['slug' => 'renamed'])->assertOk();

    $oldPath = '/ar/pages/original';
    $res = $this->getJson('/api/v1/ar/redirects/pages?path='.urlencode($oldPath));

    $res->assertStatus(301);
    expect($res->headers->get('Location'))->toContain('/ar/pages/renamed');
});

it('redirect endpoint returns 404 for an unmapped path', function (): void {
    $this->getJson('/api/v1/ar/redirects/pages?path=/ar/pages/never-existed')->assertStatus(404);
});

// ─── Loop safety + non-matching slugs ──────────────────────────────────────

it('returns 404 (no redirect) for an unknown slug with no history', function (): void {
    rdPage('live-slug');

    $this->getJson('/api/v1/ar/pages/does-not-exist')->assertStatus(404);
});

it('serves the current page normally with no redirect loop', function (): void {
    rdPage('live-slug');

    $this->getJson('/api/v1/ar/pages/live-slug')->assertOk();
});

it('does not loop when an old slug equals the current canonical (resolver guard)', function (): void {
    $page = rdPage('stable');
    // اصطناع سجل تاريخ يطابق canonical الحالي (شذوذ بيانات) — يجب ألا يُحدِث حلقة.
    PageUrlHistory::create([
        'page_id' => $page->id,
        'locale' => 'ar',
        'old_path' => '/ar/pages/stable',
        'reason' => 'manual',
    ]);

    // الـ canonical الحالي يُخدَم بحالة 200 (لا 301 على نفسه).
    $this->getJson('/api/v1/ar/pages/stable')->assertOk();
});

// ─── Locale change → 301 to current locale URL ─────────────────────────────

it('301-redirects across a locale change to the current locale URL', function (): void {
    [, $token] = rdPageAdminToken();
    $page = rdPage('shared', 'ar');

    // غيّر اللغة من ar → en (الـ slug ثابت) — يلتقط /ar/pages/shared.
    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", [
        'locale' => 'en',
    ])->assertOk();

    $res = $this->getJson('/api/v1/ar/pages/shared');
    $res->assertStatus(301);
    expect($res->headers->get('Location'))->toEndWith('/api/v1/en/pages/shared');
});

// ─── Draft slugs are NOT captured (no SEO value) ───────────────────────────

it('does not capture history for slug changes on a never-published draft', function (): void {
    [, $token] = rdPageAdminToken();
    $draft = Page::create([
        'title' => 'draft', 'locale' => 'ar', 'slug' => 'draft-old',
        'status' => 'draft', // لا published_at
    ])->fresh();

    $this->withToken($token)->putJson("/api/v1/admin/pages/{$draft->id}", [
        'slug' => 'draft-new',
    ])->assertOk();

    // المسوّدة لم تُعرَض للعامّة قط — لا تاريخ، لا 301.
    expect(PageUrlHistory::where('page_id', $draft->id)->exists())->toBeFalse();
    $this->getJson('/api/v1/ar/pages/draft-old')->assertStatus(404);
});

// ─── Partial-match safety (substring shouldn't trigger a wrong redirect) ───

it('does not partial-match a different slug', function (): void {
    [, $token] = rdPageAdminToken();
    $page = rdPage('current');
    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", [
        'slug' => 'renamed',
    ])->assertOk();
    // التاريخ يحوي /ar/pages/current — طلب slug جزئيّ (curr) يجب ألا يطابقه.
    $this->getJson('/api/v1/ar/pages/curr')->assertStatus(404);
});
