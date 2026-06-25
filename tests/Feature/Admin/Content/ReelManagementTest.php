<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Models\Reel;
use App\Models\ReelRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function reelAdminToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

function adminMakeReel(array $attrs = []): Reel
{
    return Reel::create(array_merge([
        'title' => 'ريل '.uniqid(),
        'locale' => 'ar',
        'status' => 'draft',
    ], $attrs));
}

/** أصل فيديو جاهز المعالجة في المكتبة المركزية. */
function readyVideoAsset(): MediaAsset
{
    return MediaAsset::create([
        'uuid' => 'reel-media-'.uniqid(),
        'disk' => 'public',
        'path' => 'assets/x/source.mp4',
        'filename' => 'source.mp4',
        'original_name' => 'source.mp4',
        'extension' => 'mp4',
        'size' => 2048,
        'mime_type' => 'video/mp4',
        'processing_status' => 'ready',
        'visibility' => 'public',
    ]);
}

/** ريل بفيديو جاهز — قابل للنشر/الجدولة. */
function makeReelWithReadyMedia(array $attrs = []): Reel
{
    return adminMakeReel(array_merge(['media_asset_id' => readyVideoAsset()->id], $attrs));
}

// ─── Listing / contract ──────────────────────────────────────────────────

it('lists reels with pagination meta for an authorized admin', function (): void {
    [, $token] = reelAdminToken();
    adminMakeReel(['title' => 'رياضة']);

    $res = $this->withToken($token)->getJson('/api/v1/admin/reels')->assertOk();
    assertSuccessContract($res);
    expect($res->json('meta.pagination'))->toHaveKeys(['total', 'per_page', 'current_page']);
});

// ─── Create ────────────────────────────────────────────────────────────────

it('creates a draft reel with an Arabic slug + auto uuid', function (): void {
    [$admin, $token] = reelAdminToken();

    $res = $this->withToken($token)->postJson('/api/v1/admin/reels', [
        'title' => 'مقطع رياضي', 'locale' => 'ar',
    ])->assertCreated();

    expect($res->json('data.status'))->toBe('draft');
    expect($res->json('data.slug'))->toBe('مقطع-رياضي');
    expect($res->json('data.uuid'))->not->toBeEmpty();

    $reel = Reel::first();
    expect($reel->author_id)->toBe($admin->id);
});

it('writes an initial revision on create', function (): void {
    [, $token] = reelAdminToken();

    $this->withToken($token)->postJson('/api/v1/admin/reels', [
        'title' => 'ريل بنسخة', 'locale' => 'ar',
    ])->assertCreated();

    expect(ReelRevision::count())->toBe(1);
});

it('rejects a duplicate slug within the same locale', function (): void {
    [, $token] = reelAdminToken();
    adminMakeReel(['title' => 'أ', 'locale' => 'ar', 'slug' => 'dup-reel']);

    $this->withToken($token)->postJson('/api/v1/admin/reels', [
        'title' => 'ب', 'locale' => 'ar', 'slug' => 'dup-reel',
    ])->assertStatus(422)->assertJsonValidationErrors(['slug']);
});

// ─── Update ──────────────────────────────────────────────────────────────

it('updates a reel and records a revision', function (): void {
    [, $token] = reelAdminToken();
    $reel = adminMakeReel();

    $this->withToken($token)->putJson("/api/v1/admin/reels/{$reel->id}", [
        'title' => 'عنوان محدّث', 'description' => 'وصف',
    ])->assertOk();

    expect(Reel::find($reel->id)->title)->toBe('عنوان محدّث');
    expect(ReelRevision::where('reel_id', $reel->id)->count())->toBe(1);
});

// ─── Status transitions ──────────────────────────────────────────────────

it('publishes a reel (with ready media) and stamps published_at', function (): void {
    [$admin, $token] = reelAdminToken();
    $reel = makeReelWithReadyMedia();

    $res = $this->withToken($token)->patchJson("/api/v1/admin/reels/{$reel->id}/status", [
        'status' => 'published',
    ])->assertOk();

    expect($res->json('data.status'))->toBe('published');
    $fresh = Reel::find($reel->id);
    expect($fresh->published_at)->not->toBeNull();
    expect($fresh->published_by_id)->toBe($admin->id);
});

it('requires a future date when scheduling', function (): void {
    [, $token] = reelAdminToken();
    $reel = makeReelWithReadyMedia();

    // بلا تاريخ → مرفوض
    $this->withToken($token)->patchJson("/api/v1/admin/reels/{$reel->id}/status", [
        'status' => 'scheduled',
    ])->assertStatus(422)->assertJsonValidationErrors(['published_at']);

    // تاريخ ماضٍ → مرفوض
    $this->withToken($token)->patchJson("/api/v1/admin/reels/{$reel->id}/status", [
        'status' => 'scheduled', 'published_at' => now()->subDay()->toIso8601String(),
    ])->assertStatus(422)->assertJsonValidationErrors(['published_at']);

    // تاريخ مستقبلي → مقبول
    $this->withToken($token)->patchJson("/api/v1/admin/reels/{$reel->id}/status", [
        'status' => 'scheduled', 'published_at' => now()->addDay()->toIso8601String(),
    ])->assertOk();

    expect(Reel::find($reel->id)->status->value)->toBe('scheduled');
});

// ─── Publish safeguard (hard block on non-ready media) ─────────────────────

it('hard-blocks publishing a reel without media', function (): void {
    [, $token] = reelAdminToken();
    $reel = adminMakeReel(); // لا فيديو

    $this->withToken($token)->patchJson("/api/v1/admin/reels/{$reel->id}/status", [
        'status' => 'published',
    ])->assertStatus(422);

    expect(Reel::find($reel->id)->status->value)->toBe('draft');
});

it('hard-blocks publishing a reel whose media is still processing', function (): void {
    [, $token] = reelAdminToken();
    $asset = readyVideoAsset();
    $asset->forceFill(['processing_status' => 'processing'])->save();
    $reel = adminMakeReel(['media_asset_id' => $asset->id]);

    $this->withToken($token)->patchJson("/api/v1/admin/reels/{$reel->id}/status", [
        'status' => 'published',
    ])->assertStatus(422);

    expect(Reel::find($reel->id)->status->value)->toBe('draft');
});

it('hard-blocks scheduling a reel without ready media', function (): void {
    [, $token] = reelAdminToken();
    $reel = adminMakeReel();

    $this->withToken($token)->patchJson("/api/v1/admin/reels/{$reel->id}/status", [
        'status' => 'scheduled', 'published_at' => now()->addDay()->toIso8601String(),
    ])->assertStatus(422);

    expect(Reel::find($reel->id)->status->value)->toBe('draft');
});

// ─── Delete / restore / force ──────────────────────────────────────────────

it('soft-deletes then restores a reel', function (): void {
    [, $token] = reelAdminToken();
    $reel = adminMakeReel();

    $this->withToken($token)->deleteJson("/api/v1/admin/reels/{$reel->id}")->assertOk();
    expect(Reel::withTrashed()->find($reel->id)->trashed())->toBeTrue();

    $this->withToken($token)->postJson("/api/v1/admin/reels/{$reel->id}/restore")->assertOk();
    expect(Reel::find($reel->id)->trashed())->toBeFalse();
});

it('permanently deletes a reel', function (): void {
    [, $token] = reelAdminToken();
    $reel = adminMakeReel();
    $reel->delete();

    $this->withToken($token)->deleteJson("/api/v1/admin/reels/{$reel->id}/force")->assertOk();
    expect(Reel::withTrashed()->find($reel->id))->toBeNull();
});

// ─── Sharing / SEO primitives (reused article pattern) ──────────────────────

it('exposes a stable canonical path and an OG share image from the media thumbnail', function (): void {
    [, $token] = reelAdminToken();

    // أصل وسائط بصورة poster (نفس بنية media_assets — لا og مخصّص).
    $asset = MediaAsset::create([
        'uuid' => 'reel-share-'.uniqid(),
        'disk' => 'public',
        'path' => 'assets/x/source.mp4',
        'filename' => 'source.mp4',
        'original_name' => 'source.mp4',
        'extension' => 'mp4',
        'size' => 1024,
        'mime_type' => 'video/mp4',
        'conversions' => ['poster' => ['path' => 'assets/x/poster.jpg', 'width' => 720, 'height' => 1280]],
        'processing_status' => 'ready',
        'visibility' => 'public',
    ]);
    $reel = adminMakeReel(['media_asset_id' => $asset->id]);

    $res = $this->withToken($token)->getJson("/api/v1/admin/reels/{$reel->id}")->assertOk();

    expect($res->json('data.canonical_path'))->toBe("/ar/reels/{$reel->id}-{$reel->slug}");
    expect($res->json('data.share_image'))->toContain('poster.jpg');
});

// ─── Authorization ─────────────────────────────────────────────────────────

it('denies reel access without a token', function (): void {
    $this->getJson('/api/v1/admin/reels')->assertStatus(401);
});

it('denies reel create without the permission', function (): void {
    $role = Role::findByName('reviewer', 'web');
    $role->givePermissionTo('reels.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('reviewer');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/reels', [
        'title' => 'س', 'locale' => 'ar',
    ])->assertStatus(403);
});

it('forbids publishing without reels.publish even with reels.edit', function (): void {
    $role = Role::findByName('reviewer', 'web');
    $role->givePermissionTo(['reels.view', 'reels.edit']); // لا reels.publish
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('reviewer');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $reel = adminMakeReel();

    $this->withToken($token)->patchJson("/api/v1/admin/reels/{$reel->id}/status", [
        'status' => 'published',
    ])->assertStatus(403);

    expect(Reel::find($reel->id)->status->value)->toBe('draft');
});
