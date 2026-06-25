<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function bcmSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** أصل غلاف من المكتبة المركزية — خارجي بـ poster_url مستقرّ (لا يعتمد على قرص). */
function bcmCoverAsset(string $posterUrl = 'https://cdn.allowed.test/cover.jpg'): MediaAsset
{
    return MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'kind' => 'external',
        'disk' => 'external',
        'path' => '',
        'filename' => '',
        'original_name' => 'cover',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 0,
        'checksum' => hash('sha256', Str::random()),
        'provider' => 'external',
        'poster_url' => $posterUrl,
        'visibility' => 'public',
    ]);
}

/** @return array<string,mixed> */
function bcmPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'بثّ بغلاف من المكتبة',
        'kind' => 'live',
        'source_type' => 'youtube_live',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ], $overrides);
}

it('persists cover_media_id on create and returns the resolved cover_url', function (): void {
    $token = bcmSuperToken();
    $asset = bcmCoverAsset();

    $res = $this->withToken($token)
        ->postJson('/api/v1/admin/broadcasts', bcmPayload(['cover_media_id' => $asset->id]))
        ->assertCreated();

    expect($res->json('data.cover_media_id'))->toBe($asset->id);
    expect($res->json('data.cover_url'))->toBe('https://cdn.allowed.test/cover.jpg');
});

it('rejects a cover_media_id that does not exist (FK to the central library)', function (): void {
    $token = bcmSuperToken();

    $this->withToken($token)
        ->postJson('/api/v1/admin/broadcasts', bcmPayload(['cover_media_id' => 999999]))
        ->assertStatus(422);

    expect(Broadcast::count())->toBe(0);
});

it('lets the cover be updated and cleared via the same media-library field', function (): void {
    $token = bcmSuperToken();
    $asset = bcmCoverAsset();
    $b = Broadcast::factory()->create();

    $this->withToken($token)
        ->putJson("/api/v1/admin/broadcasts/{$b->id}", ['cover_media_id' => $asset->id])
        ->assertOk()
        ->assertJsonPath('data.cover_media_id', $asset->id);

    $this->withToken($token)
        ->putJson("/api/v1/admin/broadcasts/{$b->id}", ['cover_media_id' => null])
        ->assertOk()
        ->assertJsonPath('data.cover_media_id', null);
});

it('prefers the media-library cover over the external poster_path fallback', function (): void {
    $asset = bcmCoverAsset('https://cdn.allowed.test/library-cover.jpg');
    $b = Broadcast::factory()->create([
        'cover_media_id' => $asset->id,
        'poster_path' => 'https://cdn.allowed.test/external-fallback.jpg',
    ]);

    expect($b->shareImageUrl())->toBe('https://cdn.allowed.test/library-cover.jpg');
});

it('falls back to the external poster_path when no library cover is set', function (): void {
    $b = Broadcast::factory()->create([
        'cover_media_id' => null,
        'poster_path' => 'https://cdn.allowed.test/external-fallback.jpg',
    ]);

    expect($b->shareImageUrl())->toBe('https://cdn.allowed.test/external-fallback.jpg');
});

it('renders the media-library cover on the public detail page (OG + stage)', function (): void {
    $asset = bcmCoverAsset('https://cdn.allowed.test/public-cover.jpg');
    Broadcast::factory()->live()->publicListed()->create([
        'kind' => 'live',
        'cover_media_id' => $asset->id,
        'slug' => 'cover-render-probe',
    ]);

    $this->get('/live/cover-render-probe')
        ->assertOk()
        ->assertSee('https://cdn.allowed.test/public-cover.jpg', false);
});
