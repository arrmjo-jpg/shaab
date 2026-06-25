<?php

declare(strict_types=1);

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Permission;
use App\Models\PlaylistUrlHistory;
use App\Models\Role;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\VideoPlaylist;
use App\Models\VideoUrlHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

// ─── Schema, casts, slug, uuid ──────────────────────────────────────────────

it('auto-generates uuid + arabic-aware slug and casts enums', function (): void {
    $category = VideoCategory::factory()->create(['name' => 'وثائقيات', 'locale' => 'ar']);
    $video = Video::factory()->create([
        'title' => 'فيلم وثائقي عن البحر',
        'video_category_id' => $category->id,
        'status' => VideoStatus::Published->value,
        'visibility' => VideoVisibility::Unlisted->value,
    ]);

    expect($video->uuid)->not->toBeEmpty();
    expect($video->slug)->not->toBeEmpty();
    expect($video->status)->toBe(VideoStatus::Published);
    expect($video->visibility)->toBe(VideoVisibility::Unlisted);
    expect($video->is_featured)->toBeBool();
    expect($video->category->is($category))->toBeTrue();
    expect($video->canonicalPath())->toBe("/ar/videos/{$video->id}-{$video->slug}");
});

it('keeps slug unique per locale (same slug allowed across locales)', function (): void {
    $ar = Video::factory()->create(['title' => 'Shared Title', 'locale' => 'ar']);
    $en = Video::factory()->create(['title' => 'Shared Title', 'locale' => 'en']);

    expect($ar->slug)->toBe($en->slug); // نفس الـ slug مسموح عبر اللغات
});

// ─── Scopes ─────────────────────────────────────────────────────────────────

it('scopes published and public correctly', function (): void {
    Video::factory()->create(['status' => VideoStatus::Draft->value]);
    Video::factory()->published()->create(['visibility' => VideoVisibility::Public->value]);
    Video::factory()->published()->create(['visibility' => VideoVisibility::Unlisted->value]);

    expect(Video::published()->count())->toBe(2);
    expect(Video::public()->count())->toBe(1); // المنشور + العام فقط
});

// ─── Publish-ready media guard ───────────────────────────────────────────────

it('reports no publishable media when no asset is linked', function (): void {
    $video = Video::factory()->create(['media_asset_id' => null]);
    expect($video->hasPublishableMedia())->toBeFalse();
});

// ─── Playlists: ordered pivot ────────────────────────────────────────────────

it('orders playlist videos by pivot position', function (): void {
    $playlist = VideoPlaylist::factory()->create();
    $v1 = Video::factory()->create();
    $v2 = Video::factory()->create();
    $v3 = Video::factory()->create();

    $playlist->videos()->attach([
        $v2->id => ['position' => 0],
        $v3->id => ['position' => 1],
        $v1->id => ['position' => 2],
    ]);

    expect($playlist->videos()->pluck('videos.id')->all())->toBe([$v2->id, $v3->id, $v1->id]);
    expect($playlist->canonicalPath())->toBe("/ar/playlists/{$playlist->id}-{$playlist->slug}");
    // علاقة عكسية
    expect($v1->fresh()->playlists()->count())->toBe(1);
});

// ─── URL history (videos AND playlists — foundational 301 support) ───────────

it('records video and playlist url history with working relations', function (): void {
    $video = Video::factory()->create();
    $playlist = VideoPlaylist::factory()->create();

    $vh = VideoUrlHistory::create(['video_id' => $video->id, 'locale' => 'ar', 'old_path' => '/ar/videos/old-slug']);
    $ph = PlaylistUrlHistory::create(['video_playlist_id' => $playlist->id, 'locale' => 'ar', 'old_path' => '/ar/playlists/old-slug']);

    expect($vh->video->is($video))->toBeTrue();
    expect($ph->playlist->is($playlist))->toBeTrue();
    expect($video->urlHistory()->count())->toBe(1);
    expect($playlist->urlHistory()->count())->toBe(1);
    // append-only (no updated_at column)
    expect(VideoUrlHistory::UPDATED_AT)->toBeNull();
    expect(PlaylistUrlHistory::UPDATED_AT)->toBeNull();
});

// ─── Tags reuse (Spatie, scoped 'video') ─────────────────────────────────────

it('attaches scoped video tags via Spatie HasTags', function (): void {
    $video = Video::factory()->create();
    $video->attachTag('رياضة', 'video');

    expect($video->tagsWithType('video'))->toHaveCount(1);
});

// ─── Category tree ───────────────────────────────────────────────────────────

it('supports a video-category parent/child tree', function (): void {
    $parent = VideoCategory::factory()->create(['name' => 'رياضة']);
    $child = VideoCategory::factory()->create(['name' => 'كرة قدم', 'parent_id' => $parent->id]);

    expect($child->parent->is($parent))->toBeTrue();
    expect($parent->children()->count())->toBe(1);
});

// ─── RBAC: video_library permission group seeded ─────────────────────────────

it('seeds the video_library permission group and grants it to super_admin', function (): void {
    foreach (['videos.view', 'videos.publish', 'video-playlists.manage', 'video-categories.manage', 'videos.reprocess', 'videos.sync'] as $perm) {
        expect(Permission::where('name', $perm)->exists())->toBeTrue();
    }

    $superAdmin = Role::where('name', 'super_admin')->first();
    expect($superAdmin->hasPermissionTo('videos.publish'))->toBeTrue();
    expect($superAdmin->hasPermissionTo('video-playlists.manage'))->toBeTrue();
});
