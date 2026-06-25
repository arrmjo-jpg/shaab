<?php

declare(strict_types=1);

use App\Enums\VideoStatus;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\VideoPlaylist;
use App\Support\Video\VideoMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function vlcSuper(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin', ['admin'])->plainTextToken];
}

/** محرّر يملك videos.edit فقط (+ ما يُمرّر) — لاختبار حارس كل عملية. */
function vlcEditor(string ...$perms): string
{
    $role = Role::findByName('editor', 'web');
    $role->givePermissionTo(array_merge(['videos.edit'], $perms));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function vlcUploaded(string $status = 'ready'): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'video', 'disk' => 'public',
        'path' => 'assets/'.Str::random(8).'.mp4', 'filename' => 'c.mp4', 'original_name' => 'c.mp4',
        'mime_type' => 'video/mp4', 'extension' => 'mp4', 'size' => 1024,
        'checksum' => hash('sha256', Str::random()), 'processing_status' => $status, 'visibility' => 'public',
    ]);
}

// ─── Bulk publish: invariants + partial success ─────────────────────────────

it('bulk publishes only ready videos, skipping not-ready and already-published (partial success)', function (): void {
    [$actor, $token] = vlcSuper();

    $ready = Video::factory()->create(['status' => VideoStatus::Draft->value]);
    VideoMedia::attachExternalSource($ready, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $actor);
    $ready->update(['status' => VideoStatus::Draft->value]);

    $notReady = Video::factory()->create([
        'status' => VideoStatus::Draft->value, 'source_type' => 'uploaded', 'media_asset_id' => vlcUploaded('processing')->id,
    ]);
    $already = Video::factory()->published()->create();
    VideoMedia::attachExternalSource($already, 'https://vimeo.com/123456789', $actor);
    $already->update(['status' => VideoStatus::Published->value]);

    $res = $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'publish',
        'ids' => [$ready->id, $notReady->id, $already->id],
    ])->assertOk();

    expect($res->json('data.processed'))->toBe(1);
    expect($res->json('data.requested'))->toBe(3);
    $reasons = collect($res->json('data.skipped'))->pluck('reason')->all();
    expect($reasons)->toContain('media_not_ready')->toContain('already_in_state');
    expect($ready->fresh()->status)->toBe(VideoStatus::Published);
    expect($notReady->fresh()->status)->toBe(VideoStatus::Draft);
});

it('forbids bulk publish without videos.publish (catastrophic 403)', function (): void {
    $token = vlcEditor(); // videos.edit only
    $v = Video::factory()->create();

    $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'publish', 'ids' => [$v->id],
    ])->assertStatus(403);
});

// ─── Bulk feature / unpublish / move-category ───────────────────────────────

it('bulk features videos and is idempotent on same-state', function (): void {
    [, $token] = vlcSuper();
    $a = Video::factory()->create(['is_featured' => false]);
    $b = Video::factory()->create(['is_featured' => true]); // already featured

    $res = $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'feature', 'value' => true, 'ids' => [$a->id, $b->id],
    ])->assertOk();

    expect($res->json('data.processed'))->toBe(1); // فقط a تغيّر
    expect(collect($res->json('data.skipped'))->pluck('reason'))->toContain('already_in_state');
    expect($a->fresh()->is_featured)->toBeTrue();
});

it('bulk unpublishes published videos to draft', function (): void {
    [, $token] = vlcSuper();
    $v = Video::factory()->published()->create();

    $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'unpublish', 'ids' => [$v->id],
    ])->assertOk()->assertJsonPath('data.processed', 1);

    expect($v->fresh()->status)->toBe(VideoStatus::Draft);
});

it('bulk moves videos to a category (and can clear it)', function (): void {
    [, $token] = vlcSuper();
    $cat = VideoCategory::factory()->create();
    $v = Video::factory()->create(['video_category_id' => null]);

    $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'move_category', 'video_category_id' => $cat->id, 'ids' => [$v->id],
    ])->assertOk()->assertJsonPath('data.processed', 1);
    expect($v->fresh()->video_category_id)->toBe($cat->id);

    $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'move_category', 'video_category_id' => null, 'ids' => [$v->id],
    ])->assertOk();
    expect($v->fresh()->video_category_id)->toBeNull();
});

// ─── Bulk add-to-playlist: duplicate-safe + RBAC ────────────────────────────

it('bulk adds videos to a playlist, duplicate-safe, requires playlists.manage', function (): void {
    [, $token] = vlcSuper();
    $playlist = VideoPlaylist::factory()->create();
    $v1 = Video::factory()->create();
    $v2 = Video::factory()->create();
    $playlist->videos()->attach($v2->id, ['position' => 0]); // مُضاف مسبقاً

    $res = $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'add_to_playlist', 'playlist_id' => $playlist->id, 'ids' => [$v1->id, $v2->id],
    ])->assertOk();

    expect($res->json('data.processed'))->toBe(1); // v1 فقط
    expect(collect($res->json('data.skipped'))->pluck('reason'))->toContain('already_in_playlist');
    expect($playlist->fresh()->videos()->count())->toBe(2);
});

it('forbids bulk add-to-playlist without video-playlists.manage (catastrophic 403)', function (): void {
    $token = vlcEditor(); // videos.edit only
    $playlist = VideoPlaylist::factory()->create();
    $v = Video::factory()->create();

    $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'add_to_playlist', 'playlist_id' => $playlist->id, 'ids' => [$v->id],
    ])->assertStatus(403);
});

// ─── Bulk delete + RBAC ──────────────────────────────────────────────────────

it('bulk soft-deletes videos', function (): void {
    [, $token] = vlcSuper();
    $a = Video::factory()->create();
    $b = Video::factory()->create();

    $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'delete', 'ids' => [$a->id, $b->id],
    ])->assertOk()->assertJsonPath('data.processed', 2);

    expect(Video::find($a->id))->toBeNull();
    expect(Video::withTrashed()->find($a->id)->trashed())->toBeTrue();
});

it('forbids bulk delete without videos.delete (catastrophic 403)', function (): void {
    $token = vlcEditor(); // videos.edit only
    $c = Video::factory()->create();

    $this->withToken($token)->postJson('/api/v1/admin/videos/bulk', [
        'action' => 'delete', 'ids' => [$c->id],
    ])->assertStatus(403);
});

// ─── Dashboard ───────────────────────────────────────────────────────────────

it('returns a frontend/mobile-useful dashboard payload', function (): void {
    [$actor, $token] = vlcSuper();
    Video::factory()->published()->count(2)->create(['source_type' => 'youtube', 'visibility' => 'public']);
    Video::factory()->create(['status' => VideoStatus::Draft->value, 'source_type' => 'uploaded']);
    Video::factory()->create(['source_type' => 'vimeo', 'is_featured' => true]);
    VideoPlaylist::factory()->published()->create();
    VideoCategory::factory()->create(['is_active' => true]);

    $res = $this->withToken($token)->getJson('/api/v1/admin/videos/dashboard')->assertOk();

    $res->assertJsonStructure([
        'data' => [
            'status_counts' => ['total', 'draft', 'scheduled', 'published', 'archived'],
            'source_distribution' => ['uploaded', 'youtube', 'vimeo', 'direct_mp4'],
            'processing_health' => ['processing', 'failed', 'ready'],
            'featured', 'total_views',
            'playlists' => ['total', 'published', 'featured'],
            'categories' => ['total', 'active'],
            'top_videos', 'top_categories',
        ],
    ]);
    expect($res->json('data.status_counts.published'))->toBe(2);
    expect($res->json('data.source_distribution.youtube'))->toBe(2);
    expect($res->json('data.featured'))->toBeGreaterThanOrEqual(1);
    expect($res->json('data.categories.active'))->toBeGreaterThanOrEqual(1);
});

it('requires videos.view for the dashboard', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('reviewer'); // no videos.view
    $token = $reviewer->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/videos/dashboard')->assertStatus(403);
});
