<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function pageAdminToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function makePage(array $attrs = []): Page
{
    return Page::create(array_merge([
        'title' => 'صفحة '.uniqid(),
        'locale' => 'ar',
        'status' => 'draft',
    ], $attrs));
}

// ─── Listing / contract ────────────────────────────────────────────────────

it('lists pages with pagination meta for an authorized admin', function (): void {
    [, $token] = pageAdminToken();
    makePage(['title' => 'من نحن']);

    $res = $this->withToken($token)->getJson('/api/v1/admin/pages')->assertOk();
    assertSuccessContract($res);
    expect($res->json('meta.pagination'))->toHaveKeys(['total', 'per_page', 'current_page']);
});

// ─── Create ──────────────────────────────────────────────────────────────────

it('creates a draft page with an Arabic slug + auto uuid', function (): void {
    [$admin, $token] = pageAdminToken();

    $res = $this->withToken($token)->postJson('/api/v1/admin/pages', [
        'title' => 'من نحن', 'locale' => 'ar',
    ])->assertCreated();

    expect($res->json('data.status'))->toBe('draft');
    expect($res->json('data.slug'))->toBe('من-نحن');
    expect($res->json('data.uuid'))->not->toBeEmpty();
    expect(Page::first()->author_id)->toBe($admin->id);
});

it('accepts an explicit Latin slug', function (): void {
    [, $token] = pageAdminToken();

    $res = $this->withToken($token)->postJson('/api/v1/admin/pages', [
        'title' => 'سياسة الخصوصية', 'locale' => 'ar', 'slug' => 'privacy-policy',
    ])->assertCreated();

    expect($res->json('data.slug'))->toBe('privacy-policy');
});

it('rejects a duplicate slug within the same locale', function (): void {
    [, $token] = pageAdminToken();
    makePage(['title' => 'أ', 'locale' => 'ar', 'slug' => 'dup-page']);

    $this->withToken($token)->postJson('/api/v1/admin/pages', [
        'title' => 'ب', 'locale' => 'ar', 'slug' => 'dup-page',
    ])->assertStatus(422)->assertJsonValidationErrors(['slug']);
});

it('sanitizes page content on create (strips script, keeps safe HTML)', function (): void {
    [, $token] = pageAdminToken();

    $res = $this->withToken($token)->postJson('/api/v1/admin/pages', [
        'title' => 'صفحة محتوى', 'locale' => 'ar',
        'content' => '<p>مرحباً</p><script>alert(1)</script>',
    ])->assertCreated();

    $html = (string) $res->json('data.content_html');
    expect($html)->toContain('<p>مرحباً</p>');
    expect($html)->not->toContain('<script>');
});

// ─── Model-audit compliance ──────────────────────────────────────────────────

it('records an activity-log entry on create (model-audit)', function (): void {
    [, $token] = pageAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/pages', [
        'title' => 'صفحة مدقّقة', 'locale' => 'ar',
    ])->assertCreated();

    expect(
        Activity::query()->where('log_name', 'page')->where('event', 'created')->exists()
    )->toBeTrue();
});

// ─── Update ──────────────────────────────────────────────────────────────────

it('updates a page', function (): void {
    [, $token] = pageAdminToken();
    $page = makePage();

    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", [
        'title' => 'عنوان محدّث', 'show_in_footer' => true,
    ])->assertOk();

    $fresh = Page::find($page->id);
    expect($fresh->title)->toBe('عنوان محدّث');
    expect($fresh->show_in_footer)->toBeTrue();
});

// ─── Status transitions ──────────────────────────────────────────────────────

it('publishes a page and stamps published_at + publisher', function (): void {
    [$admin, $token] = pageAdminToken();
    $page = makePage();

    $res = $this->withToken($token)->patchJson("/api/v1/admin/pages/{$page->id}/status", [
        'status' => 'published',
    ])->assertOk();

    expect($res->json('data.status'))->toBe('published');
    $fresh = Page::find($page->id);
    expect($fresh->published_at)->not->toBeNull();
    expect($fresh->published_by_id)->toBe($admin->id);
});

// ─── Delete / restore / force ────────────────────────────────────────────────

it('soft-deletes then restores a page', function (): void {
    [, $token] = pageAdminToken();
    $page = makePage();

    $this->withToken($token)->deleteJson("/api/v1/admin/pages/{$page->id}")->assertOk();
    expect(Page::withTrashed()->find($page->id)->trashed())->toBeTrue();

    $this->withToken($token)->postJson("/api/v1/admin/pages/{$page->id}/restore")->assertOk();
    expect(Page::find($page->id)->trashed())->toBeFalse();
});

it('permanently deletes a page', function (): void {
    [, $token] = pageAdminToken();
    $page = makePage();
    $page->delete();

    $this->withToken($token)->deleteJson("/api/v1/admin/pages/{$page->id}/force")->assertOk();
    expect(Page::withTrashed()->find($page->id))->toBeNull();
});

// ─── Sharing primitive ────────────────────────────────────────────────────────

it('exposes a stable slug-based canonical path', function (): void {
    [, $token] = pageAdminToken();
    $page = makePage(['slug' => 'about-us']);

    $res = $this->withToken($token)->getJson("/api/v1/admin/pages/{$page->id}")->assertOk();
    expect($res->json('data.canonical_path'))->toBe('/ar/pages/about-us');
});

// ─── Authorization ────────────────────────────────────────────────────────────

it('denies page access without a token', function (): void {
    $this->getJson('/api/v1/admin/pages')->assertStatus(401);
});

it('denies page create without the permission', function (): void {
    $role = Role::findByName('reviewer', 'web');
    $role->givePermissionTo('pages.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('reviewer');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/pages', [
        'title' => 'س', 'locale' => 'ar',
    ])->assertStatus(403);
});

it('forbids publishing without pages.publish even with pages.edit', function (): void {
    $role = Role::findByName('reviewer', 'web');
    $role->givePermissionTo(['pages.view', 'pages.edit']); // لا pages.publish
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('reviewer');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $page = makePage();

    $this->withToken($token)->patchJson("/api/v1/admin/pages/{$page->id}/status", [
        'status' => 'published',
    ])->assertStatus(403);

    expect(Page::find($page->id)->status->value)->toBe('draft');
});

// ─── M2: Extended audit-log coverage (update/delete/restore/transition) ─────

it('records an activity-log entry on update (model-audit)', function (): void {
    [, $token] = pageAdminToken();
    $page = makePage();

    $this->withToken($token)->putJson("/api/v1/admin/pages/{$page->id}", [
        'title' => 'عنوان مدقّق',
    ])->assertOk();

    expect(
        Activity::query()->where('log_name', 'page')->where('event', 'updated')->exists()
    )->toBeTrue();
});

it('records an activity-log entry on soft-delete (model-audit)', function (): void {
    [, $token] = pageAdminToken();
    $page = makePage();

    $this->withToken($token)->deleteJson("/api/v1/admin/pages/{$page->id}")->assertOk();

    expect(
        Activity::query()->where('log_name', 'page')->where('event', 'deleted')->exists()
    )->toBeTrue();
});

it('records an activity-log entry on restore (model-audit)', function (): void {
    [, $token] = pageAdminToken();
    $page = makePage();
    $page->delete();

    $this->withToken($token)->postJson("/api/v1/admin/pages/{$page->id}/restore")->assertOk();

    expect(
        Activity::query()->where('log_name', 'page')->where('event', 'restored')->exists()
    )->toBeTrue();
});

it('records a status transition (publish) as an updated activity with status in the diff', function (): void {
    [, $token] = pageAdminToken();
    $page = makePage(); // status=draft

    $this->withToken($token)->patchJson("/api/v1/admin/pages/{$page->id}/status", [
        'status' => 'published',
    ])->assertOk();

    // الحدث المُسجَّل هو updated (تغيّر الحقل status). الـ AuditsChanges trait يدمج
    // attribute_changes في properties، فنتحقّق من ظهور 'status' في الديفّ المُدمَج.
    $activity = Activity::query()
        ->where('log_name', 'page')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    // properties is a Spatie Collection (مرآة AdminActivityResource): استخدم ->all().
    $props = $activity->properties instanceof Collection
        ? $activity->properties->all()
        : (array) $activity->properties;
    expect($props['attributes']['status'] ?? null)->toBe('published');
});

// ─── M3: Cross-locale slug coexistence (per-locale uniqueness invariant) ────

it('allows the same slug to coexist across different locales', function (): void {
    [, $token] = pageAdminToken();

    // ar/shared — يُقبَل.
    $this->withToken($token)->postJson('/api/v1/admin/pages', [
        'title' => 'مشترك', 'locale' => 'ar', 'slug' => 'shared',
    ])->assertCreated();

    // en/shared — يُقبَل (لغة مختلفة).
    $this->withToken($token)->postJson('/api/v1/admin/pages', [
        'title' => 'Shared', 'locale' => 'en', 'slug' => 'shared',
    ])->assertCreated();

    // ar/shared تكرار في نفس اللغة — يُرفَض (422 على حقل slug).
    $this->withToken($token)->postJson('/api/v1/admin/pages', [
        'title' => 'تكرار', 'locale' => 'ar', 'slug' => 'shared',
    ])->assertStatus(422)->assertJsonValidationErrors(['slug']);

    expect(Page::where('slug', 'shared')->count())->toBe(2);
    expect(Page::where('slug', 'shared')->pluck('locale')->sort()->values()->all())
        ->toBe(['ar', 'en']);
});
