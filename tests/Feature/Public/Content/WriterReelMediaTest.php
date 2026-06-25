<?php

declare(strict_types=1);

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\MediaAsset;
use App\Models\Reel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
    Queue::fake();
});

/** كاتب (اسم فريد — مستقلّ عن بقيّة الملفّات). */
function reelMediaWriter(): array
{
    $u = User::factory()->create(['is_writer' => true]);
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

function reelOwnedVideo(User $owner): MediaAsset
{
    return (new StoreMediaAssetAction)->handle(UploadedFile::fake()->create('reel.mp4', 1024, 'video/mp4'), $owner);
}

function reelOwnedImage(User $owner): MediaAsset
{
    return (new StoreMediaAssetAction)->handle(UploadedFile::fake()->image('p.jpg', 120, 120), $owner);
}

function reelMediaPayload(array $extra): array
{
    return array_merge(['title' => 'ريل', 'locale' => 'ar'], $extra);
}

// ─── 1. ريل بفيديو يملكه الكاتب → 201 + ربط ───────────────────────────────
it('lets a writer attach an owned uploaded reel video', function (): void {
    [$writer, $token] = reelMediaWriter();
    $asset = reelOwnedVideo($writer);

    $res = $this->withToken($token)->postJson('/api/v1/reels', reelMediaPayload(['media_asset_id' => $asset->id]));

    $res->assertCreated();
    $reel = Reel::latest('id')->first();
    expect($reel->media_asset_id)->toBe($asset->id);
});

// ─── 2. منع فيديو لا يملكه الكاتب → 422 ───────────────────────────────────
it('forbids attaching a reel video owned by another writer (IDOR)', function (): void {
    [, $token] = reelMediaWriter();
    $other = User::factory()->create(['is_writer' => true]);
    $foreign = reelOwnedVideo($other);

    $this->withToken($token)->postJson('/api/v1/reels', reelMediaPayload(['media_asset_id' => $foreign->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('media_asset_id');

    expect(Reel::count())->toBe(0);
});

// ─── 3. أصل مملوك لكنّه ليس فيديو (صورة) → 422 ────────────────────────────
it('rejects a non-video owned asset as a reel source', function (): void {
    [$writer, $token] = reelMediaWriter();
    $img = reelOwnedImage($writer);

    $this->withToken($token)->postJson('/api/v1/reels', reelMediaPayload(['media_asset_id' => $img->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('media_asset_id');
});
