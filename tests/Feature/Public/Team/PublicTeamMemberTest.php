<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Models\TeamMember;
use App\Models\TeamMemberUrlHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeActiveMember(array $attrs = []): TeamMember
{
    return TeamMember::create(array_merge([
        'name' => 'عضو '.uniqid(),
        'job_title' => 'مصوّر',
        'status' => 'active',
    ], $attrs));
}

function makePublicImage(): MediaAsset
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
        'size' => 1000,
        'width' => 600,
        'height' => 600,
        'conversions' => ['thumb' => ['path' => 'media/thumb.webp'], 'medium' => ['path' => 'media/medium.webp']],
        'visibility' => 'public',
    ]);
}

// ─── List (grouped, active-only) ─────────────────────────────────────────────

it('lists active members grouped by department (ordered)', function (): void {
    makeActiveMember(['name' => 'مصوّر أول', 'department' => 'التصوير', 'sort_order' => 0]);
    makeActiveMember(['name' => 'مبرمج', 'department' => 'التقنية', 'sort_order' => 1]);
    makeActiveMember(['name' => 'مصوّر ثانٍ', 'department' => 'التصوير', 'sort_order' => 2]);

    $res = $this->getJson('/api/v1/team')->assertOk();
    $groups = $res->json('data');

    expect($groups)->toHaveCount(2);
    expect($groups[0]['department'])->toBe('التصوير');
    expect($groups[0]['members'])->toHaveCount(2);
    expect($groups[1]['department'])->toBe('التقنية');
});

it('excludes inactive members from the public list', function (): void {
    makeActiveMember(['name' => 'ظاهر', 'status' => 'active']);
    makeActiveMember(['name' => 'مخفي', 'status' => 'inactive']);

    $res = $this->getJson('/api/v1/team')->assertOk();
    $names = collect($res->json('data'))->flatMap(fn ($g) => collect($g['members'])->pluck('name'))->all();

    expect($names)->toContain('ظاهر');
    expect($names)->not->toContain('مخفي');
});

// ─── Show (+ Person JSON-LD) ─────────────────────────────────────────────────

it('shows an active member with Person JSON-LD and sameAs', function (): void {
    $img = makePublicImage();
    makeActiveMember([
        'name' => 'فخري النجار',
        'job_title' => 'مصوّر صحفي',
        'slug' => 'fakhri',
        'department' => 'التصوير',
        'avatar_asset_id' => $img->id,
        'social_links' => ['facebook' => 'https://facebook.com/fakhri', 'website' => 'https://fakhri.example'],
    ]);

    $res = $this->getJson('/api/v1/team/fakhri')->assertOk();

    expect($res->json('data.name'))->toBe('فخري النجار');
    expect($res->json('data.canonical_path'))->toBe('/team/fakhri');

    $sd = $res->json('data.seo.structured_data');
    expect($sd['@type'])->toBe('Person');
    expect($sd['jobTitle'])->toBe('مصوّر صحفي');
    expect($sd['sameAs'])->toContain('https://facebook.com/fakhri');
    expect($sd['sameAs'])->toContain('https://fakhri.example');
    expect($sd['image'])->not->toBeNull();

    // og:type=profile لصفحات الأشخاص.
    expect($res->json('data.seo.og.type'))->toBe('profile');
});

it('returns 404 for an inactive member', function (): void {
    makeActiveMember(['slug' => 'hidden', 'status' => 'inactive']);

    $this->getJson('/api/v1/team/hidden')->assertStatus(404);
});

it('returns 404 for a non-existent slug', function (): void {
    $this->getJson('/api/v1/team/ghost')->assertStatus(404);
});

// ─── 301 redirect (URL history) ──────────────────────────────────────────────

it('redirects 301 from an old slug to the current member (show endpoint)', function (): void {
    $member = makeActiveMember(['slug' => 'new-slug', 'status' => 'active']);
    TeamMemberUrlHistory::create(['team_member_id' => $member->id, 'old_path' => '/team/old-slug']);

    $res = $this->getJson('/api/v1/team/old-slug')->assertStatus(301);
    expect($res->headers->get('Location'))->toContain('/api/v1/team/new-slug');
});

it('resolves an old full path to a 301 via the redirect endpoint', function (): void {
    $member = makeActiveMember(['slug' => 'current', 'status' => 'active']);
    TeamMemberUrlHistory::create(['team_member_id' => $member->id, 'old_path' => '/team/previous']);

    $res = $this->getJson('/api/v1/redirects/team?path=/team/previous')->assertStatus(301);
    expect($res->headers->get('Location'))->toContain('/team/current');
});

it('returns 404 from the redirect endpoint when no history matches', function (): void {
    $this->getJson('/api/v1/redirects/team?path=/team/unknown')->assertStatus(404);
});

// ─── Sitemap ─────────────────────────────────────────────────────────────────

it('includes active members in the team sitemap', function (): void {
    makeActiveMember(['slug' => 'in-map', 'status' => 'active']);
    makeActiveMember(['slug' => 'not-in-map', 'status' => 'inactive']);

    $res = $this->get('/sitemap-team.xml')->assertOk();
    $res->assertSee('/team/in-map', false);
    $res->assertDontSee('/team/not-in-map', false);
});

it('references the team sitemap in the sitemap index', function (): void {
    $this->get('/sitemap.xml')->assertOk()->assertSee('sitemap-team.xml', false);
});
