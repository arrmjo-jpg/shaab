<?php

declare(strict_types=1);

use App\Models\PlaylistUrlHistory;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\VideoPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function vlbToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** دور بصلاحيات عرض فقط (لا manage) — لاختبار الحارس. */
function vlbViewerToken(string ...$perms): string
{
    $role = Role::findByName('reviewer', 'web');
    $role->givePermissionTo($perms);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('reviewer');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

// ─── Video categories: CRUD + tree + hierarchy guards ───────────────────────

it('creates a category and a nested child, returned as a tree', function (): void {
    $token = vlbToken();

    $parent = $this->withToken($token)->postJson('/api/v1/admin/video-categories', [
        'name' => 'رياضة', 'locale' => 'ar',
    ])->assertCreated()->json('data.id');

    $this->withToken($token)->postJson('/api/v1/admin/video-categories', [
        'name' => 'كرة قدم', 'locale' => 'ar', 'parent_id' => $parent,
    ])->assertCreated();

    $tree = $this->withToken($token)->getJson('/api/v1/admin/video-categories?locale=ar')->assertOk();
    expect($tree->json('data.0.id'))->toBe($parent);
    expect($tree->json('data.0.children.0.name'))->toBe('كرة قدم');
});

it('enforces hierarchy guards (self-parent, locale match, max depth)', function (): void {
    $token = vlbToken();
    $root = VideoCategory::factory()->create(['locale' => 'ar']);

    // أب-ذاتي
    $this->withToken($token)->putJson("/api/v1/admin/video-categories/{$root->id}", [
        'parent_id' => $root->id,
    ])->assertStatus(422);

    // عدم تطابق اللغة
    $this->withToken($token)->postJson('/api/v1/admin/video-categories', [
        'name' => 'EN child', 'locale' => 'en', 'parent_id' => $root->id,
    ])->assertStatus(422);

    // تجاوز العمق الأقصى (MAX_DEPTH = 3)
    $d2 = VideoCategory::factory()->create(['locale' => 'ar', 'parent_id' => $root->id]);
    $d3 = VideoCategory::factory()->create(['locale' => 'ar', 'parent_id' => $d2->id]);
    $this->withToken($token)->postJson('/api/v1/admin/video-categories', [
        'name' => 'too deep', 'locale' => 'ar', 'parent_id' => $d3->id,
    ])->assertStatus(422);
});

it('reorders sibling categories with move up/down', function (): void {
    $token = vlbToken();
    $a = VideoCategory::factory()->create(['locale' => 'ar', 'sort_order' => 0]);
    $b = VideoCategory::factory()->create(['locale' => 'ar', 'sort_order' => 1]);

    $this->withToken($token)->patchJson("/api/v1/admin/video-categories/{$b->id}/move", ['direction' => 'up'])->assertOk();

    expect($b->fresh()->sort_order)->toBeLessThan($a->fresh()->sort_order);
});

it('soft deletes, restores, and force-deletes a category (detaching its videos)', function (): void {
    $token = vlbToken();
    $cat = VideoCategory::factory()->create();
    $video = Video::factory()->create(['video_category_id' => $cat->id]);

    $this->withToken($token)->deleteJson("/api/v1/admin/video-categories/{$cat->id}")->assertOk();
    $this->withToken($token)->postJson("/api/v1/admin/video-categories/{$cat->id}/restore")->assertOk();

    $this->withToken($token)->deleteJson("/api/v1/admin/video-categories/{$cat->id}/force")->assertOk();
    expect(VideoCategory::withTrashed()->find($cat->id))->toBeNull();
    expect($video->fresh()->video_category_id)->toBeNull(); // فُصِل (nullOnDelete)
});

it('requires video-categories.manage to create a category', function (): void {
    $token = vlbViewerToken('video-categories.view');

    $this->withToken($token)->postJson('/api/v1/admin/video-categories', [
        'name' => 'x', 'locale' => 'ar',
    ])->assertStatus(403);
});

// ─── Playlists: CRUD + url history + attach/detach/reorder ──────────────────

it('creates a playlist', function (): void {
    $token = vlbToken();

    $this->withToken($token)->postJson('/api/v1/admin/video-playlists', [
        'title' => 'أفضل الوثائقيات', 'locale' => 'ar',
    ])->assertCreated()->assertJsonPath('data.status', 'draft');
});

it('records playlist url history when the slug changes (301 support)', function (): void {
    $token = vlbToken();
    $playlist = VideoPlaylist::factory()->create(['locale' => 'ar', 'title' => 'Original', 'slug' => 'original']);
    $oldPath = $playlist->canonicalPath();

    $this->withToken($token)->putJson("/api/v1/admin/video-playlists/{$playlist->id}", [
        'slug' => 'renamed-playlist',
    ])->assertOk();

    expect($playlist->fresh()->slug)->toBe('renamed-playlist');
    expect(PlaylistUrlHistory::where('old_path', $oldPath)->where('video_playlist_id', $playlist->id)->exists())->toBeTrue();
});

it('attaches, reorders, and detaches playlist videos with stable positions', function (): void {
    $token = vlbToken();
    $playlist = VideoPlaylist::factory()->create();
    $v1 = Video::factory()->create();
    $v2 = Video::factory()->create();
    $v3 = Video::factory()->create();

    // إضافة (تُلحَق بالترتيب)
    $this->withToken($token)->postJson("/api/v1/admin/video-playlists/{$playlist->id}/videos", [
        'video_ids' => [$v1->id, $v2->id, $v3->id],
    ])->assertOk();
    expect($playlist->fresh()->videos()->pluck('videos.id')->all())->toBe([$v1->id, $v2->id, $v3->id]);

    // إعادة ترتيب (سحب)
    $this->withToken($token)->patchJson("/api/v1/admin/video-playlists/{$playlist->id}/reorder", [
        'ordered_ids' => [$v3->id, $v1->id, $v2->id],
    ])->assertOk();
    expect($playlist->fresh()->videos()->pluck('videos.id')->all())->toBe([$v3->id, $v1->id, $v2->id]);

    // إزالة
    $this->withToken($token)->deleteJson("/api/v1/admin/video-playlists/{$playlist->id}/videos/{$v1->id}")->assertOk();
    expect($playlist->fresh()->videos()->pluck('videos.id')->all())->toBe([$v3->id, $v2->id]);
});

it('ignores duplicate attaches and non-member reorder ids', function (): void {
    $token = vlbToken();
    $playlist = VideoPlaylist::factory()->create();
    $v1 = Video::factory()->create();
    $outsider = Video::factory()->create();

    $this->withToken($token)->postJson("/api/v1/admin/video-playlists/{$playlist->id}/videos", ['video_ids' => [$v1->id, $v1->id]])->assertOk();
    expect($playlist->fresh()->videos()->count())->toBe(1); // لا تكرار

    // إعادة ترتيب بمعرّف غير عضو — يُتجاهَل بأمان
    $this->withToken($token)->patchJson("/api/v1/admin/video-playlists/{$playlist->id}/reorder", ['ordered_ids' => [$outsider->id, $v1->id]])->assertOk();
    expect($playlist->fresh()->videos()->pluck('videos.id')->all())->toBe([$v1->id]);
});

it('reorders a playlist larger than 200 videos (reorder cap matches editor load cap)', function (): void {
    $token = vlbToken();
    $author = User::factory()->create();
    $playlist = VideoPlaylist::factory()->create();

    // 201 فيديو مُسنَد مباشرةً (الإسناد لكل طلب محدود بـ 200) — يتجاوز سقف 200 القديم.
    $ids = Video::factory()->count(201)->create(['author_id' => $author->id])->pluck('id')->all();
    $attach = [];
    foreach ($ids as $i => $id) {
        $attach[$id] = ['position' => $i];
    }
    $playlist->videos()->attach($attach);

    $reversed = array_reverse($ids);
    $this->withToken($token)->patchJson("/api/v1/admin/video-playlists/{$playlist->id}/reorder", [
        'ordered_ids' => $reversed,
    ])->assertOk();

    expect($playlist->fresh()->videos()->pluck('videos.id')->all())->toBe($reversed);
});

it('rejects attaching non-existent video ids with 422 (batched existence)', function (): void {
    $token = vlbToken();
    $playlist = VideoPlaylist::factory()->create();
    $valid = Video::factory()->create();

    $res = $this->withToken($token)->postJson("/api/v1/admin/video-playlists/{$playlist->id}/videos", [
        'video_ids' => [$valid->id, 999999999],
    ]);

    $res->assertStatus(422);
    expect($res->json('errors'))->toHaveKey('video_ids');
    expect($playlist->fresh()->videos()->count())->toBe(0); // لا إسناد جزئي
});

it('soft deletes, restores and force-deletes a playlist (cascading pivot only)', function (): void {
    $token = vlbToken();
    $playlist = VideoPlaylist::factory()->create();
    $video = Video::factory()->create();
    $playlist->videos()->attach($video->id, ['position' => 0]);

    $this->withToken($token)->deleteJson("/api/v1/admin/video-playlists/{$playlist->id}")->assertOk();
    $this->withToken($token)->postJson("/api/v1/admin/video-playlists/{$playlist->id}/restore")->assertOk();
    $this->withToken($token)->deleteJson("/api/v1/admin/video-playlists/{$playlist->id}/force")->assertOk();

    expect(VideoPlaylist::withTrashed()->find($playlist->id))->toBeNull();
    expect(Video::find($video->id))->not->toBeNull(); // الفيديو نفسه باقٍ
    expect(DB::table('playlist_video')->where('video_playlist_id', $playlist->id)->count())->toBe(0);
});

it('requires video-playlists.manage to create a playlist', function (): void {
    $token = vlbViewerToken('video-playlists.view');

    $this->withToken($token)->postJson('/api/v1/admin/video-playlists', [
        'title' => 'x', 'locale' => 'ar',
    ])->assertStatus(403);
});
