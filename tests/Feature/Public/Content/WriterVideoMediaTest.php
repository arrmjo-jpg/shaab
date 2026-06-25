<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    Queue::fake(); // لا ترميز فعليّ — نختبر الربط/الملكيّة فقط
});

/** كاتب (اسم فريد — مستقلّ عن بقيّة الملفّات). */
function vidWriter(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

function ownedVideoAsset(User $owner): MediaAsset
{
    return (new StoreMediaAssetAction)->handle(UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'), $owner);
}

function ownedImageAssetForVideo(User $owner): MediaAsset
{
    return (new StoreMediaAssetAction)->handle(UploadedFile::fake()->image('p.jpg', 120, 120), $owner);
}

function vidPayload(array $extra): array
{
    return array_merge(['title' => 'فيديو', 'locale' => 'ar'], $extra);
}

// ─── 1. رفع: أصل فيديو يملكه الكاتب → 201 + ربط (source_type=uploaded) ─────
it('lets a writer attach an owned uploaded video', function (): void {
    [$writer, $token] = vidWriter();
    $asset = ownedVideoAsset($writer);

    $res = $this->withToken($token)->postJson('/api/v1/videos', vidPayload(['media_asset_id' => $asset->id]));

    $res->assertCreated();
    $video = Video::latest('id')->first();
    expect($video->media_asset_id)->toBe($asset->id);
    expect($video->source_type)->toBe('uploaded');
});

// ─── 2. رابط: مصدر خارجيّ (يوتيوب) → 201 + أصل خارجيّ مربوط ────────────────
it('lets a writer attach an external video URL', function (): void {
    [, $token] = vidWriter();

    $res = $this->withToken($token)->postJson(
        '/api/v1/videos',
        vidPayload(['source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']),
    );

    $res->assertCreated();
    $video = Video::latest('id')->first();
    expect($video->media_asset_id)->not->toBeNull();
    expect(MediaAsset::find($video->media_asset_id)->kind)->toBe('external');
});

// ─── 3. منع رفع لا يملكه الكاتب (أصل كاتب آخر) → 422 ───────────────────────
it('forbids attaching an uploaded video owned by another writer (IDOR)', function (): void {
    [, $token] = vidWriter();
    $other = User::factory()->create(['is_writer' => true]);
    $foreign = ownedVideoAsset($other);

    $this->withToken($token)->postJson('/api/v1/videos', vidPayload(['media_asset_id' => $foreign->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('media_asset_id');

    expect(Video::count())->toBe(0);
});

// ─── 4. أصل مملوك لكنّه ليس فيديو (صورة) → 422 ────────────────────────────
it('rejects a non-video owned asset as a video source', function (): void {
    [$writer, $token] = vidWriter();
    $img = ownedImageAssetForVideo($writer);

    $this->withToken($token)->postJson('/api/v1/videos', vidPayload(['media_asset_id' => $img->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('media_asset_id');
});

// ─── 5. رابط غير مدعوم → 422 ──────────────────────────────────────────────
it('rejects an unsupported source URL', function (): void {
    [, $token] = vidWriter();

    $this->withToken($token)->postJson('/api/v1/videos', vidPayload(['source_url' => 'https://example.com/not-a-video']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('source_url');
});
