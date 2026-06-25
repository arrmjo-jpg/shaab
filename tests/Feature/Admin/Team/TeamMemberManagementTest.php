<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\TeamMember;
use App\Models\TeamMemberUrlHistory;
use App\Models\User;
use App\Support\Content\TeamMemberRedirectResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function teamAdminToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function makeTeamMember(array $attrs = []): TeamMember
{
    return TeamMember::create(array_merge([
        'name' => 'عضو '.uniqid(),
        'job_title' => 'مصوّر',
        'status' => 'active',
    ], $attrs));
}

function makePublicImageAsset(array $conversions = ['thumb' => ['path' => 'media/thumb.webp']]): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'file',
        'disk' => 'public',
        'path' => 'media/original.jpg',
        'filename' => 'original.jpg',
        'original_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 12345,
        'width' => 800,
        'height' => 800,
        'conversions' => $conversions,
        'visibility' => 'public',
    ]);
}

// ─── Listing / contract ────────────────────────────────────────────────────

it('lists team members with pagination meta for an authorized admin', function (): void {
    [, $token] = teamAdminToken();
    makeTeamMember(['name' => 'فخري النجار']);

    $res = $this->withToken($token)->getJson('/api/v1/admin/team-members')->assertOk();
    assertSuccessContract($res);
    expect($res->json('meta.pagination'))->toHaveKeys(['total', 'per_page', 'current_page']);
});

// ─── Create ──────────────────────────────────────────────────────────────────

it('creates an active member with an Arabic slug + auto uuid', function (): void {
    [, $token] = teamAdminToken();

    $res = $this->withToken($token)->postJson('/api/v1/admin/team-members', [
        'name' => 'فخري النجار', 'job_title' => 'مصوّر',
    ])->assertCreated();

    expect($res->json('data.status'))->toBe('active');
    expect($res->json('data.slug'))->toBe('فخري-النجار');
    expect($res->json('data.uuid'))->not->toBeEmpty();
    expect($res->json('data.job_title'))->toBe('مصوّر');
});

it('accepts an explicit Latin slug + structured social links', function (): void {
    [, $token] = teamAdminToken();

    $res = $this->withToken($token)->postJson('/api/v1/admin/team-members', [
        'name' => 'Ahmad', 'job_title' => 'Developer', 'slug' => 'ahmad-dev',
        'social_links' => [
            'facebook' => 'https://facebook.com/ahmad',
            'website' => 'https://ahmad.dev',
            'unknown_key' => 'https://evil.example', // يُسقط (مفتاح غير مسموح)
        ],
    ])->assertCreated();

    expect($res->json('data.slug'))->toBe('ahmad-dev');
    expect($res->json('data.social_links.facebook'))->toBe('https://facebook.com/ahmad');
    expect($res->json('data.social_links.website'))->toBe('https://ahmad.dev');
    expect($res->json('data.social_links.unknown_key'))->toBeNull();
});

it('rejects a duplicate slug', function (): void {
    [, $token] = teamAdminToken();
    makeTeamMember(['name' => 'أ', 'slug' => 'dup-member']);

    $this->withToken($token)->postJson('/api/v1/admin/team-members', [
        'name' => 'ب', 'job_title' => 'مهندس', 'slug' => 'dup-member',
    ])->assertStatus(422)->assertJsonValidationErrors(['slug']);
});

it('rejects an invalid social link URL', function (): void {
    [, $token] = teamAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/team-members', [
        'name' => 'س', 'job_title' => 'مهندس',
        'social_links' => ['facebook' => 'not-a-url'],
    ])->assertStatus(422)->assertJsonValidationErrors(['social_links.facebook']);
});

it('sanitizes bio on create (strips script, keeps safe HTML)', function (): void {
    [, $token] = teamAdminToken();

    $res = $this->withToken($token)->postJson('/api/v1/admin/team-members', [
        'name' => 'محتوى', 'job_title' => 'كاتب',
        'bio' => '<p>نبذة</p><script>alert(1)</script>',
    ])->assertCreated();

    $html = (string) $res->json('data.bio_html');
    expect($html)->toContain('<p>نبذة</p>');
    expect($html)->not->toContain('<script>');
});

// ─── Avatar via MediaAsset (CDN/conversions/governance) ─────────────────────

it('links an avatar MediaAsset and exposes resolved CDN urls (thumb included)', function (): void {
    [, $token] = teamAdminToken();
    $asset = makePublicImageAsset();

    $res = $this->withToken($token)->postJson('/api/v1/admin/team-members', [
        'name' => 'بصورة', 'job_title' => 'مصوّر', 'avatar_asset_id' => $asset->id,
    ])->assertCreated();

    expect($res->json('data.avatar_asset_id'))->toBe($asset->id);
    expect($res->json('data.avatar.id'))->toBe($asset->id);
    expect($res->json('data.avatar.thumb'))->not->toBeNull();
    expect($res->json('data.avatar.width'))->toBe(800);
});

it('returns a null avatar when none is linked (null-safe)', function (): void {
    [, $token] = teamAdminToken();
    $member = makeTeamMember();

    $res = $this->withToken($token)->getJson("/api/v1/admin/team-members/{$member->id}")->assertOk();
    expect($res->json('data.avatar'))->toBeNull();
    expect($res->json('data.avatar_asset_id'))->toBeNull();
});

it('rejects a non-existent avatar_asset_id', function (): void {
    [, $token] = teamAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/team-members', [
        'name' => 'خطأ', 'job_title' => 'مصوّر', 'avatar_asset_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors(['avatar_asset_id']);
});

// ─── Model-audit compliance ──────────────────────────────────────────────────

it('records an activity-log entry on create (model-audit)', function (): void {
    [, $token] = teamAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/team-members', [
        'name' => 'مدقّق', 'job_title' => 'مصوّر',
    ])->assertCreated();

    expect(
        Activity::query()->where('log_name', 'team_member')->where('event', 'created')->exists()
    )->toBeTrue();
});

// ─── Update ──────────────────────────────────────────────────────────────────

it('updates a team member', function (): void {
    [, $token] = teamAdminToken();
    $member = makeTeamMember();

    $this->withToken($token)->putJson("/api/v1/admin/team-members/{$member->id}", [
        'name' => 'اسم محدّث', 'department' => 'التصوير',
    ])->assertOk();

    $fresh = TeamMember::find($member->id);
    expect($fresh->name)->toBe('اسم محدّث');
    expect($fresh->department)->toBe('التصوير');
});

// ─── Status toggle ─────────────────────────────────────────────────────────────

it('toggles status active <-> inactive', function (): void {
    [, $token] = teamAdminToken();
    $member = makeTeamMember(['status' => 'active']);

    $res = $this->withToken($token)->patchJson("/api/v1/admin/team-members/{$member->id}/status", [
        'status' => 'inactive',
    ])->assertOk();

    expect($res->json('data.status'))->toBe('inactive');
    expect(TeamMember::find($member->id)->status->value)->toBe('inactive');
});

// ─── Reorder ──────────────────────────────────────────────────────────────────

it('reorders team members by id list', function (): void {
    [, $token] = teamAdminToken();
    $a = makeTeamMember(['name' => 'A', 'sort_order' => 0]);
    $b = makeTeamMember(['name' => 'B', 'sort_order' => 1]);
    $c = makeTeamMember(['name' => 'C', 'sort_order' => 2]);

    $this->withToken($token)->patchJson('/api/v1/admin/team-members/reorder', [
        'ids' => [$c->id, $a->id, $b->id],
    ])->assertOk();

    expect(TeamMember::find($c->id)->sort_order)->toBe(0);
    expect(TeamMember::find($a->id)->sort_order)->toBe(1);
    expect(TeamMember::find($b->id)->sort_order)->toBe(2);
});

// ─── Delete / restore / force ────────────────────────────────────────────────

it('soft-deletes then restores a team member', function (): void {
    [, $token] = teamAdminToken();
    $member = makeTeamMember();

    $this->withToken($token)->deleteJson("/api/v1/admin/team-members/{$member->id}")->assertOk();
    expect(TeamMember::withTrashed()->find($member->id)->trashed())->toBeTrue();

    $this->withToken($token)->postJson("/api/v1/admin/team-members/{$member->id}/restore")->assertOk();
    expect(TeamMember::find($member->id)->trashed())->toBeFalse();
});

it('permanently deletes a team member', function (): void {
    [, $token] = teamAdminToken();
    $member = makeTeamMember();
    $member->delete();

    $this->withToken($token)->deleteJson("/api/v1/admin/team-members/{$member->id}/force")->assertOk();
    expect(TeamMember::withTrashed()->find($member->id))->toBeNull();
});

// ─── Canonical path (Arabic-only, no locale prefix) ─────────────────────────

it('exposes a slug-based canonical path without a locale prefix', function (): void {
    [, $token] = teamAdminToken();
    $member = makeTeamMember(['slug' => 'about-photographer']);

    $res = $this->withToken($token)->getJson("/api/v1/admin/team-members/{$member->id}")->assertOk();
    expect($res->json('data.canonical_path'))->toBe('/team/about-photographer');
});

// ─── URL history + redirect resolution (301 safeguard) ──────────────────────

it('records url history on slug change for an active member and resolves the old path', function (): void {
    [, $token] = teamAdminToken();
    $member = makeTeamMember(['name' => 'فخري', 'slug' => 'old-slug', 'status' => 'active']);

    $this->withToken($token)->putJson("/api/v1/admin/team-members/{$member->id}", [
        'slug' => 'new-slug',
    ])->assertOk();

    expect(TeamMemberUrlHistory::where('old_path', '/team/old-slug')->exists())->toBeTrue();

    $resolved = TeamMemberRedirectResolver::resolveByPath('/team/old-slug');
    expect($resolved?->id)->toBe($member->id);
    expect($resolved?->slug)->toBe('new-slug');
});

it('does not record url history for an inactive member slug change', function (): void {
    [, $token] = teamAdminToken();
    $member = makeTeamMember(['slug' => 'hidden-old', 'status' => 'inactive']);

    $this->withToken($token)->putJson("/api/v1/admin/team-members/{$member->id}", [
        'slug' => 'hidden-new',
    ])->assertOk();

    expect(TeamMemberUrlHistory::where('old_path', '/team/hidden-old')->exists())->toBeFalse();
});

it('does not redirect when the old path equals the current canonical (self-reference guard)', function (): void {
    $member = makeTeamMember(['slug' => 'same', 'status' => 'active']);
    // مدخل تاريخيّ يشير إلى المسار القانوني الحالي نفسه — يجب ألا يُعيد التوجيه (منع A→A).
    TeamMemberUrlHistory::create(['team_member_id' => $member->id, 'old_path' => '/team/same']);

    expect(TeamMemberRedirectResolver::resolveByPath('/team/same'))->toBeNull();
});

it('does not resolve an old path to an inactive member', function (): void {
    $member = makeTeamMember(['slug' => 'gone-new', 'status' => 'inactive']);
    TeamMemberUrlHistory::create(['team_member_id' => $member->id, 'old_path' => '/team/gone-old']);

    expect(TeamMemberRedirectResolver::resolveByPath('/team/gone-old'))->toBeNull();
});

// ─── Authorization ────────────────────────────────────────────────────────────

it('denies team access without a token', function (): void {
    $this->getJson('/api/v1/admin/team-members')->assertStatus(401);
});

it('denies team create without the permission', function (): void {
    $role = Role::findByName('reviewer', 'web');
    $role->givePermissionTo('team.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('reviewer');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/team-members', [
        'name' => 'س', 'job_title' => 'مهندس',
    ])->assertStatus(403);
});
