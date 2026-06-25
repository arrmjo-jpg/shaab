<?php

declare(strict_types=1);

use App\Models\Reel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pubReel(string $status = 'published'): Reel
{
    return Reel::create([
        'title' => 'ريل '.uniqid(),
        'locale' => 'ar',
        'status' => $status,
        'published_at' => $status === 'published' ? now()->subMinute() : null,
    ]);
}

function reelGuest(string $device = 'reel-device-1'): array
{
    return ['X-Client-Id' => $device];
}

// ─── Reels are a first-class engageable type (reusing existing infra) ───────

it('records a like on a reel via the existing public engagement endpoint', function (): void {
    $reel = pubReel();

    $res = $this->withHeaders(reelGuest())
        ->postJson("/api/v1/engagement/reel/{$reel->id}/react", ['reaction' => 'like'])
        ->assertOk();

    expect($res->json('data.reaction'))->toBe('like');
    expect($res->json('data.metrics.likes'))->toBe(1);
});

it('toggles favorite on a reel and reports state', function (): void {
    $reel = pubReel();

    $this->withHeaders(reelGuest())
        ->postJson("/api/v1/engagement/reel/{$reel->id}/favorite")->assertOk();

    $res = $this->withHeaders(reelGuest())
        ->getJson("/api/v1/engagement/reel/{$reel->id}")->assertOk();

    expect($res->json('data.favorited'))->toBeTrue();
    expect($res->json('data.metrics'))->toHaveKeys(['views', 'likes', 'dislikes', 'favorites']);
});

it('returns 404 for an unpublished reel target', function (): void {
    $reel = pubReel('draft');

    $this->withHeaders(reelGuest())
        ->getJson("/api/v1/engagement/reel/{$reel->id}")
        ->assertStatus(404);
});

// ─── Admin reel list exposes the unified metrics (no N+1, no reel counters) ─

it('exposes engagement metrics in the admin reel list', function (): void {
    seedRoles();
    $u = User::factory()->create();
    $u->assignRole('super_admin');
    $token = $u->createToken('t', ['admin'])->plainTextToken;

    $reel = pubReel();
    $this->withHeaders(reelGuest('x'))
        ->postJson("/api/v1/engagement/reel/{$reel->id}/react", ['reaction' => 'like'])->assertOk();

    $res = $this->withToken($token)->getJson('/api/v1/admin/reels')->assertOk();
    $row = collect($res->json('data'))->firstWhere('id', $reel->id);

    expect($row['metrics']['likes'])->toBe(1);
    expect($row['metrics'])->toHaveKeys(['views', 'likes', 'dislikes', 'favorites']);
});
