<?php

declare(strict_types=1);

use App\Enums\VideoStatus;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoUrlHistory;
use App\Support\Video\VideoMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function vlSuperToken(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin', ['admin'])->plainTextToken];
}

/** محرّر يملك view/create/edit فقط (لا publish) — لاختبار حارس الصلاحية. */
function vlEditorToken(): string
{
    $role = Role::findByName('editor', 'web');
    $role->givePermissionTo(['videos.view', 'videos.create', 'videos.edit']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function vlUploadedAsset(string $status = 'ready'): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'video',
        'disk' => 'public',
        'path' => 'assets/'.Str::random(8).'.mp4',
        'filename' => 'clip.mp4',
        'original_name' => 'clip.mp4',
        'mime_type' => 'video/mp4',
        'extension' => 'mp4',
        'size' => 2048,
        'checksum' => hash('sha256', Str::random()),
        'processing_status' => $status,
        'visibility' => 'public',
    ]);
}

// ─── DRAFT: media SOURCE required, readiness NOT required ───────────────────

it('creates a draft with an external source (no readiness required)', function (): void {
    [, $token] = vlSuperToken();

    $res = $this->withToken($token)->postJson('/api/v1/admin/videos', [
        'title' => 'فيديو يوتيوب',
        'locale' => 'ar',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertCreated();

    expect($res->json('data.status'))->toBe('draft');
    expect($res->json('data.source_type'))->toBe('youtube');
    expect($res->json('data.media_asset_id'))->not->toBeNull();
});

it('creates a draft with an uploaded asset that is still processing (not ready)', function (): void {
    [, $token] = vlSuperToken();
    $asset = vlUploadedAsset('processing'); // ليست جاهزة بعد

    $res = $this->withToken($token)->postJson('/api/v1/admin/videos', [
        'title' => 'فيديو مرفوع قيد المعالجة',
        'locale' => 'ar',
        'media_asset_id' => $asset->id,
    ])->assertCreated();

    expect($res->json('data.status'))->toBe('draft');
    expect($res->json('data.source_type'))->toBe('uploaded');
});

it('rejects creating a video without ANY media source (source required even for draft)', function (): void {
    [, $token] = vlSuperToken();

    $this->withToken($token)->postJson('/api/v1/admin/videos', [
        'title' => 'بلا مصدر',
        'locale' => 'ar',
    ])->assertStatus(422)->assertJsonValidationErrors(['media_asset_id', 'source_url']);
});

it('rejects an uploaded source that is not a video asset', function (): void {
    [, $token] = vlSuperToken();
    $image = MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'image', 'disk' => 'public', 'path' => 'assets/x.png',
        'filename' => 'x.png', 'original_name' => 'x.png', 'mime_type' => 'image/png', 'extension' => 'png',
        'size' => 100, 'checksum' => hash('sha256', 'img'), 'visibility' => 'public',
    ]);

    $this->withToken($token)->postJson('/api/v1/admin/videos', [
        'title' => 'مصدر خاطئ', 'locale' => 'ar', 'media_asset_id' => $image->id,
    ])->assertStatus(422)->assertJsonValidationErrors('media_asset_id');
});

// ─── PUBLISH/SCHEDULE: readiness ENFORCED ────────────────────────────────────

it('blocks publishing a draft whose uploaded media is not ready', function (): void {
    [, $token] = vlSuperToken();
    $asset = vlUploadedAsset('processing');
    $video = Video::factory()->create(['status' => VideoStatus::Draft->value, 'media_asset_id' => $asset->id, 'source_type' => 'uploaded']);

    $this->withToken($token)->patchJson("/api/v1/admin/videos/{$video->id}/status", [
        'status' => 'published',
    ])->assertStatus(422);

    expect($video->fresh()->status)->toBe(VideoStatus::Draft);
});

it('publishes a draft once its uploaded media is ready', function (): void {
    [, $token] = vlSuperToken();
    $asset = vlUploadedAsset('ready');
    $video = Video::factory()->create(['status' => VideoStatus::Draft->value, 'media_asset_id' => $asset->id, 'source_type' => 'uploaded']);

    $this->withToken($token)->patchJson("/api/v1/admin/videos/{$video->id}/status", [
        'status' => 'published',
    ])->assertOk();

    expect($video->fresh()->status)->toBe(VideoStatus::Published);
    expect($video->fresh()->published_at)->not->toBeNull();
});

it('publishes a draft with an external source immediately', function (): void {
    [$actor, $token] = vlSuperToken();
    $video = Video::factory()->create(['status' => VideoStatus::Draft->value]);
    VideoMedia::attachExternalSource($video, 'https://vimeo.com/123456789', $actor);

    $this->withToken($token)->patchJson("/api/v1/admin/videos/{$video->id}/status", [
        'status' => 'published',
    ])->assertOk();

    expect($video->fresh()->status)->toBe(VideoStatus::Published);
});

it('requires a date when scheduling', function (): void {
    [, $token] = vlSuperToken();
    $asset = vlUploadedAsset('ready');
    $video = Video::factory()->create(['status' => VideoStatus::Draft->value, 'media_asset_id' => $asset->id, 'source_type' => 'uploaded']);

    $this->withToken($token)->patchJson("/api/v1/admin/videos/{$video->id}/status", [
        'status' => 'scheduled', // بلا تاريخ
    ])->assertStatus(422);

    $this->withToken($token)->patchJson("/api/v1/admin/videos/{$video->id}/status", [
        'status' => 'scheduled', 'published_at' => now()->addDay()->toISOString(),
    ])->assertOk();

    expect($video->fresh()->status)->toBe(VideoStatus::Scheduled);
});

it('forbids publishing without the videos.publish permission', function (): void {
    $token = vlEditorToken();
    $asset = vlUploadedAsset('ready');
    $video = Video::factory()->create(['status' => VideoStatus::Draft->value, 'media_asset_id' => $asset->id, 'source_type' => 'uploaded']);

    $this->withToken($token)->patchJson("/api/v1/admin/videos/{$video->id}/status", [
        'status' => 'published',
    ])->assertStatus(403);
});

// ─── Update: slug change records 301 url history ─────────────────────────────

it('records video url history when the slug changes', function (): void {
    [, $token] = vlSuperToken();
    $video = Video::factory()->create(['locale' => 'ar', 'title' => 'Original', 'slug' => 'original']);
    $oldPath = $video->canonicalPath();

    $this->withToken($token)->putJson("/api/v1/admin/videos/{$video->id}", [
        'slug' => 'renamed-slug',
    ])->assertOk();

    expect($video->fresh()->slug)->toBe('renamed-slug');
    expect(VideoUrlHistory::where('old_path', $oldPath)->where('video_id', $video->id)->exists())->toBeTrue();
});

// ─── Lifecycle: delete / restore / force-delete ──────────────────────────────

it('soft deletes, restores, then force deletes a video (releasing owned uploaded asset)', function (): void {
    [, $token] = vlSuperToken();
    $asset = vlUploadedAsset('ready');
    $video = Video::factory()->create(['media_asset_id' => $asset->id, 'source_type' => 'uploaded']);

    $this->withToken($token)->deleteJson("/api/v1/admin/videos/{$video->id}")->assertOk();
    expect(Video::find($video->id))->toBeNull();

    $this->withToken($token)->postJson("/api/v1/admin/videos/{$video->id}/restore")->assertOk();
    expect(Video::find($video->id))->not->toBeNull();

    $this->withToken($token)->deleteJson("/api/v1/admin/videos/{$video->id}/force")->assertOk();
    expect(Video::withTrashed()->find($video->id))->toBeNull();
    expect(MediaAsset::find($asset->id))->toBeNull(); // الأصل المملوك نُظِّف
});

// ─── List + stats + RBAC boundaries ──────────────────────────────────────────

it('lists, filters and reports stats', function (): void {
    [, $token] = vlSuperToken();
    Video::factory()->published()->count(2)->create();
    Video::factory()->create(['status' => VideoStatus::Draft->value]);

    $this->withToken($token)->getJson('/api/v1/admin/videos?filter[status]=published')
        ->assertOk()->assertJsonStructure(['data', 'meta' => ['pagination']]);

    $stats = $this->withToken($token)->getJson('/api/v1/admin/videos/stats')->assertOk();
    expect($stats->json('data.published'))->toBe(2);
    expect($stats->json('data.draft'))->toBeGreaterThanOrEqual(1);
});

it('enforces auth and permission boundaries', function (): void {
    $this->getJson('/api/v1/admin/videos')->assertStatus(401);

    $u = User::factory()->create();
    $u->assignRole('super_admin');
    $userToken = $u->createToken('public', ['user'])->plainTextToken; // ability=user
    $this->withToken($userToken)->getJson('/api/v1/admin/videos')->assertStatus(403);

    $reviewer = User::factory()->create();
    $reviewer->assignRole('reviewer'); // بلا videos.view
    $rToken = $reviewer->createToken('admin', ['admin'])->plainTextToken;
    $this->withToken($rToken)->getJson('/api/v1/admin/videos')->assertStatus(403);
});
