<?php

declare(strict_types=1);

use App\Actions\Admin\Broadcast\EmergencyShutdownAction;
use App\Models\Broadcast;
use App\Models\Engagement;
use App\Models\User;
use App\Support\Broadcast\BroadcastPresenceControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

// ─── Helpers (أسماء فريدة عالمياً) ───────────────────────────────────────────

/** @return array{0:User,1:string} مستخدم عام + رمز بقدرة user. */
function brUser(): array
{
    $u = User::factory()->create();
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

function brBroadcast(string $status = 'live', array $attrs = []): Broadcast
{
    $times = match ($status) {
        'scheduled' => ['scheduled_at' => now()->addHour()],
        'live' => ['started_at' => now()->subMinutes(5)],
        'ended' => ['started_at' => now()->subHour(), 'ended_at' => now()->subMinute()],
        default => [],
    };

    return Broadcast::factory()->create(array_merge(['status' => $status, 'is_public' => true], $times, $attrs));
}

function brUrl(Broadcast $b): string
{
    return "/api/v1/broadcasts/{$b->id}/reaction";
}

// ─── Auth gate (guests denied) ───────────────────────────────────────────────

it('denies an unauthenticated guest from reacting', function (): void {
    $b = brBroadcast();

    $this->postJson(brUrl($b), ['reaction' => 'like'])->assertStatus(401);
});

// ─── Like / dislike / toggle / remove ────────────────────────────────────────

it('lets an authenticated user like a broadcast', function (): void {
    [, $token] = brUser();
    $b = brBroadcast();

    $res = $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();

    expect($res->json('data.reaction'))->toBe('like');
    expect($res->json('data.metrics.likes'))->toBe(1);
    expect($res->json('data.metrics.dislikes'))->toBe(0);
});

it('lets an authenticated user dislike a broadcast', function (): void {
    [, $token] = brUser();
    $b = brBroadcast();

    $res = $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'dislike'])->assertOk();

    expect($res->json('data.reaction'))->toBe('dislike');
    expect($res->json('data.metrics.dislikes'))->toBe(1);
    expect($res->json('data.metrics.likes'))->toBe(0);
});

it('toggles like into dislike (exclusive single reaction)', function (): void {
    [, $token] = brUser();
    $b = brBroadcast();

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();
    $res = $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'dislike'])->assertOk();

    expect($res->json('data.reaction'))->toBe('dislike');
    expect($res->json('data.metrics.likes'))->toBe(0);
    expect($res->json('data.metrics.dislikes'))->toBe(1);
});

it('toggles dislike into like (exclusive single reaction)', function (): void {
    [, $token] = brUser();
    $b = brBroadcast();

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'dislike'])->assertOk();
    $res = $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();

    expect($res->json('data.reaction'))->toBe('like');
    expect($res->json('data.metrics.likes'))->toBe(1);
    expect($res->json('data.metrics.dislikes'))->toBe(0);
});

it('removes a reaction via DELETE', function (): void {
    [, $token] = brUser();
    $b = brBroadcast();

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();
    $res = $this->withToken($token)->deleteJson(brUrl($b))->assertOk();

    expect($res->json('data.reaction'))->toBeNull();
    expect($res->json('data.metrics.likes'))->toBe(0);
});

it('returns the current user reaction and counts via GET', function (): void {
    [, $token] = brUser();
    $b = brBroadcast();

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'dislike'])->assertOk();
    $res = $this->withToken($token)->getJson(brUrl($b))->assertOk();

    expect($res->json('data.reaction'))->toBe('dislike');
    expect($res->json('data.metrics.dislikes'))->toBe(1);
});

// ─── Anti-abuse: idempotency / no inflation / aggregate correctness ──────────

it('toggles the same reaction off (platform-consistent) without inflating the count', function (): void {
    [$user, $token] = brUser();
    $b = brBroadcast();

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertOk()->assertJsonPath('data.metrics.likes', 1);
    $res = $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();

    expect($res->json('data.reaction'))->toBeNull(); // نفس التفاعل يُلغيه (مرآة المنصّة)
    expect($res->json('data.metrics.likes'))->toBe(0);

    // لا تضخيم: صفّ تفاعل واحد كحدّ أقصى لكل (مستخدم + هدف).
    $rows = Engagement::query()
        ->where('engageable_type', (new Broadcast)->getMorphClass())
        ->where('engageable_id', $b->id)
        ->where('user_id', $user->id)
        ->count();
    expect($rows)->toBeLessThanOrEqual(1);
});

it('never inflates the like count under repeated identical reactions', function (): void {
    [, $token] = brUser();
    $b = brBroadcast();

    foreach (range(1, 5) as $_) {
        $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();
    }

    $likes = $this->withToken($token)->getJson(brUrl($b))->assertOk()->json('data.metrics.likes');
    expect($likes)->toBeLessThanOrEqual(1); // عدّاد محدود — لا تكرار
});

it('aggregates counts across distinct users correctly', function (): void {
    [$u1] = brUser();
    [$u2] = brUser();
    [$u3] = brUser();
    $b = brBroadcast();

    Sanctum::actingAs($u1, ['user']);
    $this->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();
    Sanctum::actingAs($u2, ['user']);
    $this->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();
    Sanctum::actingAs($u3, ['user']);
    $res = $this->postJson(brUrl($b), ['reaction' => 'dislike'])->assertOk();

    expect($res->json('data.metrics.likes'))->toBe(2);
    expect($res->json('data.metrics.dislikes'))->toBe(1);
});

// ─── Moderation integration (B6 — real, not faked) ───────────────────────────

it('denies reaction when the user is banned', function (): void {
    [$user, $token] = brUser();
    $b = brBroadcast();
    BroadcastPresenceControl::ban($b->id, 'u'.$user->id);

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertStatus(403);
});

it('denies reaction when the audience is closed', function (): void {
    [, $token] = brUser();
    $b = brBroadcast();
    BroadcastPresenceControl::close($b->id);

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertStatus(403);
});

it('denies reaction after an emergency shutdown', function (): void {
    [, $token] = brUser();
    $b = brBroadcast('live');
    (new EmergencyShutdownAction)->handle($b->fresh());

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertStatus(403);
});

// ─── Visibility policy ───────────────────────────────────────────────────────

it('denies reaction on draft/archived broadcasts (no public page)', function (string $status): void {
    [, $token] = brUser();
    $b = brBroadcast($status);

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])->assertStatus(404);
})->with(['draft', 'archived']);

it('allows reaction on ended and offline broadcasts (explicit product decision)', function (string $status): void {
    [, $token] = brUser();
    $b = brBroadcast($status);

    $this->withToken($token)->postJson(brUrl($b), ['reaction' => 'like'])
        ->assertOk()
        ->assertJsonPath('data.reaction', 'like');
})->with(['ended', 'offline']);

// ─── Public surface integration (B4) ─────────────────────────────────────────

it('surfaces aggregate reaction counts in the public broadcast detail', function (): void {
    [$u1] = brUser();
    [$u2] = brUser();
    $b = brBroadcast('live', ['slug' => 'react-shape']);

    Sanctum::actingAs($u1, ['user']);
    $this->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();
    Sanctum::actingAs($u2, ['user']);
    $this->postJson(brUrl($b), ['reaction' => 'like'])->assertOk();

    $detail = $this->getJson('/api/v1/live/react-shape')->assertOk();
    expect($detail->json('data.metrics.likes'))->toBe(2);
    expect($detail->json('data.metrics.dislikes'))->toBe(0);
});
