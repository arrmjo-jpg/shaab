<?php

declare(strict_types=1);

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Jobs\TranscodeVideoAssetJob;
use App\Models\EngagementCounter;
use App\Models\MediaAsset;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPlaylist;
use App\Support\Video\VideoMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function vaoSuper(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin', ['admin'])->plainTextToken];
}

/** محرّر يملك videos.edit فقط (+ ما يُمرّر) — لاختبار حارس كل عملية. */
function vaoEditor(string ...$perms): string
{
    $role = Role::findByName('editor', 'web');
    $role->givePermissionTo(array_merge(['videos.edit'], $perms));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function vaoUploaded(string $status = 'ready'): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(), 'kind' => 'video', 'disk' => 'public',
        'path' => 'assets/'.Str::random(8).'.mp4', 'filename' => 'c.mp4', 'original_name' => 'c.mp4',
        'mime_type' => 'video/mp4', 'extension' => 'mp4', 'size' => 1024,
        'checksum' => hash('sha256', Str::random()), 'processing_status' => $status, 'visibility' => 'public',
    ]);
}

/** فيديو خارجي منشور وقابل للتشغيل (overrides لضبط الرؤية/الحالة). */
function vaoExternalVideo(User $actor, string $url, array $overrides = []): Video
{
    $v = Video::factory()->create();
    VideoMedia::attachExternalSource($v, $url, $actor);
    $v->update(array_merge([
        'status' => VideoStatus::Published->value,
        'published_at' => now()->subDay(),
        'visibility' => VideoVisibility::Public->value,
    ], $overrides));

    return $v->fresh();
}

function vaoCounter(Video $video, array $metrics): void
{
    EngagementCounter::create(array_merge([
        'engageable_type' => (new Video)->getMorphClass(),
        'engageable_id' => $video->id,
        'views' => 0, 'likes' => 0, 'dislikes' => 0, 'favorites' => 0,
    ], $metrics));
}

// ─── Analytics → engagement aggregates ───────────────────────────────────────

it('aggregates real engagement totals across all non-deleted videos (and excludes soft-deleted)', function (): void {
    [$actor, $token] = vaoSuper();

    $a = vaoExternalVideo($actor, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    $b = vaoExternalVideo($actor, 'https://vimeo.com/123456789');
    $draft = Video::factory()->create(['status' => VideoStatus::Draft->value]);
    $trashed = Video::factory()->create();

    vaoCounter($a, ['views' => 100, 'likes' => 10, 'dislikes' => 2, 'favorites' => 5]);
    vaoCounter($b, ['views' => 50, 'likes' => 20, 'dislikes' => 0, 'favorites' => 10]);
    vaoCounter($draft, ['views' => 1000, 'likes' => 1, 'dislikes' => 0, 'favorites' => 0]);
    vaoCounter($trashed, ['views' => 500, 'likes' => 99, 'dislikes' => 9, 'favorites' => 9]);
    $trashed->delete(); // soft-delete → must drop from totals

    $res = $this->withToken($token)->getJson('/api/v1/admin/videos/analytics')->assertOk();

    $res->assertJsonStructure([
        'data' => [
            'engagement' => ['views', 'likes', 'dislikes', 'favorites'],
            'top_playlists', 'trending',
        ],
    ]);

    // 100+50+1000 = 1150 (trashed 500 excluded); likes 10+20+1 = 31; dislikes 2; favorites 5+10 = 15
    expect($res->json('data.engagement.views'))->toBe(1150);
    expect($res->json('data.engagement.likes'))->toBe(31);
    expect($res->json('data.engagement.dislikes'))->toBe(2);
    expect($res->json('data.engagement.favorites'))->toBe(15);
});

it('ranks trending by weighted engagement and excludes non-public videos', function (): void {
    [$actor, $token] = vaoSuper();

    $low = vaoExternalVideo($actor, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    $high = vaoExternalVideo($actor, 'https://vimeo.com/123456789');
    // قابل للتشغيل لكنه غير مُدرَج (ليس عاماً) — يجب استبعاده من الرائج رغم تفاعله الضخم.
    $hidden = vaoExternalVideo($actor, 'https://www.youtube.com/watch?v=oHg5SJYRHA0', [
        'visibility' => VideoVisibility::Unlisted->value,
    ]);

    vaoCounter($low, ['views' => 10, 'likes' => 0, 'dislikes' => 0, 'favorites' => 0]);   // score 10
    vaoCounter($high, ['views' => 0, 'likes' => 20, 'dislikes' => 0, 'favorites' => 5]);  // score 80+30 = 110
    vaoCounter($hidden, ['views' => 9999, 'likes' => 9999, 'dislikes' => 0, 'favorites' => 9999]);

    $res = $this->withToken($token)->getJson('/api/v1/admin/videos/analytics')->assertOk();

    $ids = collect($res->json('data.trending'))->pluck('id')->all();
    expect($ids)->toContain($high->id)->toContain($low->id)->not->toContain($hidden->id);
    expect($res->json('data.trending.0.id'))->toBe($high->id); // الأعلى وزناً أولاً
});

it('lists top playlists ordered by video count', function (): void {
    [, $token] = vaoSuper();

    $p1 = VideoPlaylist::factory()->create();
    $p2 = VideoPlaylist::factory()->create();
    VideoPlaylist::factory()->create(); // empty playlist

    $v1 = Video::factory()->create();
    $v2 = Video::factory()->create();
    $p1->videos()->attach([$v1->id => ['position' => 0], $v2->id => ['position' => 1]]);
    $p2->videos()->attach([$v1->id => ['position' => 0]]);

    $res = $this->withToken($token)->getJson('/api/v1/admin/videos/analytics')->assertOk();

    expect($res->json('data.top_playlists.0.id'))->toBe($p1->id);
    expect($res->json('data.top_playlists.0.videos_count'))->toBe(2);
});

it('requires videos.view for analytics', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('reviewer'); // no videos.view
    $token = $reviewer->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/videos/analytics')->assertStatus(403);
});

// ─── Operations → processing health + needs-attention + publish queue ─────────

it('reports processing health, the needs-attention list, and the publish queue', function (): void {
    [, $token] = vaoSuper();

    $failed = Video::factory()->create([
        'source_type' => 'uploaded', 'media_asset_id' => vaoUploaded('failed')->id, 'title' => 'فشل الترميز',
    ]);
    $processing = Video::factory()->create([
        'source_type' => 'uploaded', 'media_asset_id' => vaoUploaded('processing')->id,
    ]);
    // مرفوع جاهز — لا يظهر في قائمة الانتباه.
    Video::factory()->create([
        'source_type' => 'uploaded', 'media_asset_id' => vaoUploaded('ready')->id,
    ]);

    $overdue = Video::factory()->create([
        'status' => VideoStatus::Scheduled->value, 'published_at' => now()->subHour(),
    ]);
    Video::factory()->create([
        'status' => VideoStatus::Scheduled->value, 'published_at' => now()->addDay(),
    ]);

    $res = $this->withToken($token)->getJson('/api/v1/admin/videos/operations')->assertOk();

    $res->assertJsonStructure([
        'data' => [
            'processing_health' => ['processing', 'failed'],
            'needs_attention' => [['id', 'title', 'locale', 'media_uuid', 'processing_status', 'updated_at']],
            'publish_queue' => ['scheduled_total', 'due_now', 'upcoming'],
        ],
    ]);

    expect($res->json('data.processing_health.failed'))->toBe(1);
    expect($res->json('data.processing_health.processing'))->toBe(1);

    $attentionIds = collect($res->json('data.needs_attention'))->pluck('id')->all();
    expect($attentionIds)->toContain($failed->id)->toContain($processing->id);

    expect($res->json('data.publish_queue.scheduled_total'))->toBe(2);
    expect($res->json('data.publish_queue.due_now'))->toBe(1); // المستحقّ الآن فقط
    expect($res->json('data.publish_queue.upcoming.0.id'))->toBe($overdue->id); // مُرتَّب تصاعدياً
    expect($res->json('data.publish_queue.upcoming.0.overdue'))->toBeTrue();
});

it('requires videos.view for operations', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('reviewer'); // no videos.view
    $token = $reviewer->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/videos/operations')->assertStatus(403);
});

// ─── Reprocess uploaded video media (retry) ──────────────────────────────────

it('reprocesses an uploaded video and re-queues transcoding', function (): void {
    Queue::fake();
    [, $token] = vaoSuper();
    $asset = vaoUploaded('failed');
    $video = Video::factory()->create(['source_type' => 'uploaded', 'media_asset_id' => $asset->id]);

    $this->withToken($token)->postJson("/api/v1/admin/videos/{$video->id}/reprocess")
        ->assertOk()
        ->assertJsonPath('message', __('video.reprocess_queued'));

    expect($asset->fresh()->processing_status)->toBe('queued');
    Queue::assertPushed(TranscodeVideoAssetJob::class);
});

it('rejects reprocessing a video with an external source (422)', function (): void {
    Queue::fake();
    [$actor, $token] = vaoSuper();
    $video = Video::factory()->create();
    VideoMedia::attachExternalSource($video, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $actor);

    $this->withToken($token)->postJson("/api/v1/admin/videos/{$video->id}/reprocess")
        ->assertStatus(422)
        ->assertJsonPath('message', __('video.reprocess_unavailable'));

    Queue::assertNotPushed(TranscodeVideoAssetJob::class);
});

it('requires videos.reprocess permission (videos.edit alone is forbidden)', function (): void {
    $token = vaoEditor(); // videos.edit only
    $asset = vaoUploaded('failed');
    $video = Video::factory()->create(['source_type' => 'uploaded', 'media_asset_id' => $asset->id]);

    $this->withToken($token)->postJson("/api/v1/admin/videos/{$video->id}/reprocess")
        ->assertStatus(403);
});
