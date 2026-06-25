<?php

declare(strict_types=1);

use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Media\ExternalVideoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function extEditor(): array
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return [$u, $u->createToken('admin-token', ['admin'])->plainTextToken];
}

// ─── Resolver (allow-list + provider detection) ─────────────────────────

it('resolves mandatory providers', function (string $url, string $provider, ?string $id): void {
    $r = ExternalVideoResolver::resolve($url);
    expect($r)->not->toBeNull();
    expect($r['provider'])->toBe($provider);
    if ($id !== null) {
        expect($r['provider_id'])->toBe($id);
    }
    expect($r['embed_url'])->toBeString()->not->toBe('');
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
    'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
    'youtube shorts' => ['https://www.youtube.com/shorts/abc123XYZ_', 'youtube', 'abc123XYZ_'],
    'vimeo' => ['https://vimeo.com/76979871', 'vimeo', '76979871'],
    'tiktok' => ['https://www.tiktok.com/@user/video/7212345678901234567', 'tiktok', '7212345678901234567'],
    'instagram reel' => ['https://www.instagram.com/reel/CxyZ-12ab/', 'instagram', 'CxyZ-12ab'],
    'direct mp4' => ['https://cdn.example.com/clips/news.mp4', 'mp4', null],
]);

it('resolves optional providers (facebook + x)', function (): void {
    expect(ExternalVideoResolver::resolve('https://www.facebook.com/page/videos/1234567890')['provider'])->toBe('facebook');
    expect(ExternalVideoResolver::resolve('https://x.com/user/status/1234567890123456')['provider'])->toBe('x');
});

it('rejects unsupported / spoofed / non-http urls', function (string $url): void {
    expect(ExternalVideoResolver::resolve($url))->toBeNull();
})->with([
    'spoofed host' => ['https://youtube.com.attacker.com/watch?v=abcdef'],
    'random site' => ['https://evil.example.com/video/123'],
    'javascript scheme' => ['javascript:alert(1)'],
    'non-video page' => ['https://example.com/article'],
]);

// ─── Store endpoint ──────────────────────────────────────────────────────

it('stores an external video as a central media asset', function (): void {
    [, $token] = extEditor();

    $res = $this->withToken($token)->postJson('/api/v1/admin/media/external', [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertCreated();

    expect($res->json('data.kind'))->toBe('external');
    expect($res->json('data.is_external'))->toBeTrue();
    expect($res->json('data.provider'))->toBe('youtube');
    expect($res->json('data.embed_url'))->toContain('youtube.com/embed/dQw4w9WgXcQ');
    expect($res->json('data.url'))->toContain('youtube.com/embed/dQw4w9WgXcQ');

    $asset = MediaAsset::first();
    expect($asset->kind)->toBe('external');
    expect($asset->disk)->toBe('external');
});

it('dedupes the same external video by provider id', function (): void {
    [, $token] = extEditor();
    $payload = ['url' => 'https://vimeo.com/76979871'];

    $first = $this->withToken($token)->postJson('/api/v1/admin/media/external', $payload)->assertCreated();
    $second = $this->withToken($token)->postJson('/api/v1/admin/media/external', $payload)->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    expect(MediaAsset::count())->toBe(1);
});

it('rejects an unsupported url with 422', function (): void {
    [, $token] = extEditor();

    $this->withToken($token)->postJson('/api/v1/admin/media/external', [
        'url' => 'https://evil.example.com/x',
    ])->assertStatus(422)->assertJsonPath('errors.url.0', __('media.external.unsupported'));
});

// ─── Resolve (preview) endpoint ──────────────────────────────────────────

it('previews a provider via the resolve endpoint without persisting', function (): void {
    [, $token] = extEditor();

    $res = $this->withToken($token)->postJson('/api/v1/admin/media/external/resolve', [
        'url' => 'https://www.tiktok.com/@user/video/7212345678901234567',
    ])->assertOk();

    expect($res->json('data.provider'))->toBe('tiktok');
    expect(MediaAsset::count())->toBe(0);
});

// ─── Library list includes external video ────────────────────────────────

it('lists external videos in the library under the external type filter', function (): void {
    [, $token] = extEditor();
    $this->withToken($token)->postJson('/api/v1/admin/media/external', [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertCreated();

    $videos = $this->withToken($token)->getJson('/api/v1/admin/media?type=external')->assertOk();
    expect($videos->json('meta.pagination.total'))->toBe(1);
    expect($videos->json('data.0.is_external'))->toBeTrue();
});

it('requires media.upload to add external video', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $token = $user->createToken('t', ['admin'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/media/external', [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
    ])->assertForbidden();
});
