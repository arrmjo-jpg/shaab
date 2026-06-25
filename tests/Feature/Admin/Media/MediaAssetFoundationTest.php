<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
    Storage::fake('uploads');
});

function mediaLibrarian(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Upload ────────────────────────────────────────────────────────────

it('uploads an image asset with checksum, dimensions and WebP conversions', function (): void {
    [, $token] = mediaLibrarian();

    $res = $this->withToken($token)->post('/api/v1/admin/media', [
        'file' => UploadedFile::fake()->image('photo.jpg', 1600, 1200),
    ]);

    $res->assertCreated();
    assertSuccessContract($res);
    expect($res->json('data.is_image'))->toBeTrue();
    expect($res->json('data.width'))->toBe(1600);
    expect($res->json('data.height'))->toBe(1200);

    $asset = MediaAsset::first();
    expect($asset->checksum)->not->toBeNull();
    expect(strlen($asset->checksum))->toBe(64); // sha256 hex

    // sync queue → conversion job ran → derivatives generated + stored
    $asset->refresh();
    expect($asset->conversions)->toHaveKeys(['thumb', 'medium']);
    Storage::disk('uploads')->assertExists($asset->conversions['thumb']['path']);
    Storage::disk('uploads')->assertExists($asset->conversions['medium']['path']);
    Storage::disk('uploads')->assertExists($asset->path);
});

it('records an activity log entry on asset upload', function (): void {
    [, $token] = mediaLibrarian();

    $this->withToken($token)->post('/api/v1/admin/media', [
        'file' => UploadedFile::fake()->image('a.png', 400, 400),
    ])->assertCreated();

    expect(Activity::where('log_name', 'media')->count())->toBeGreaterThanOrEqual(1);
});

// ─── Dedupe ────────────────────────────────────────────────────────────

it('deduplicates identical uploads by checksum', function (): void {
    [, $token] = mediaLibrarian();

    // Same bytes both times: build a fixed file and re-upload it
    $tmp = UploadedFile::fake()->image('dup.jpg', 500, 500);
    $bytes = file_get_contents($tmp->getRealPath());

    $make = function () use ($bytes) {
        $path = tempnam(sys_get_temp_dir(), 'dup').'.jpg';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'dup.jpg', 'image/jpeg', null, true);
    };

    $first = $this->withToken($token)->post('/api/v1/admin/media', ['file' => $make()]);
    $first->assertCreated();
    $firstUuid = $first->json('data.uuid');

    $second = $this->withToken($token)->post('/api/v1/admin/media', ['file' => $make()]);
    $second->assertCreated();

    // Same asset returned, no duplicate row
    expect($second->json('data.uuid'))->toBe($firstUuid);
    expect(MediaAsset::count())->toBe(1);
});

// ─── Video ─────────────────────────────────────────────────────────────

it('uploads a video asset without generating image conversions', function (): void {
    [, $token] = mediaLibrarian();

    $res = $this->withToken($token)->post('/api/v1/admin/media', [
        'file' => UploadedFile::fake()->create('clip.mp4', 800, 'video/mp4'),
    ]);

    $res->assertCreated();
    expect($res->json('data.is_image'))->toBeFalse();

    $asset = MediaAsset::first();
    expect($asset->conversions)->toBeNull();
    // thumb/medium fall back to the original url for non-images
    expect($res->json('data.thumb'))->toBe($res->json('data.url'));
});

// ─── Validation / permissions ──────────────────────────────────────────

it('rejects unsupported file types', function (): void {
    [, $token] = mediaLibrarian();

    $this->withToken($token)->post('/api/v1/admin/media', [
        'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ])->assertUnprocessable();
});

it('requires the media.upload permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->post('/api/v1/admin/media', [
        'file' => UploadedFile::fake()->image('x.jpg'),
    ])->assertForbidden();
});

it('rejects unauthenticated uploads', function (): void {
    $this->postJson('/api/v1/admin/media', [])->assertUnauthorized();
});
