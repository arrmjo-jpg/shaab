<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\User;
use App\Support\Broadcast\BroadcastPresence;
use App\Support\Broadcast\BroadcastPresenceControl;
use App\Support\Engagement\EngagementActor;
use App\Support\Scheduler\SchedulerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers (أسماء فريدة عالمياً عبر مجموعة Pest) ────────────────────────────

function preBroadcast(string $status = 'live', array $attrs = []): Broadcast
{
    $times = match ($status) {
        'scheduled' => ['scheduled_at' => now()->addHour()],
        'live' => ['started_at' => now()->subMinutes(5)],
        'ended' => ['started_at' => now()->subHour(), 'ended_at' => now()->subMinute()],
        default => [],
    };

    return Broadcast::factory()->create(array_merge([
        'status' => $status,
        'is_public' => true,
    ], $times, $attrs));
}

/** يفكّ حمولة الرمز الموقّع (الجزء الأول base64) لتأكيد ربط الهوية/النوع. */
function preTokenPayload(string $token): string
{
    return (string) base64_decode(strtr(explode('.', $token)[0], '-_', '+/'), true);
}

function preUrl(Broadcast $b, string $suffix = ''): string
{
    return "/api/v1/broadcasts/{$b->id}/presence{$suffix}";
}

// ─── Join + signed identity ─────────────────────────────────────────────────

it('issues a signed guest session token and counts the viewer', function (): void {
    $b = preBroadcast('live');

    $res = $this->withHeaders(['X-Client-Id' => 'guest-a'])->postJson(preUrl($b, '/join'))->assertOk();

    expect($res->json('data.token'))->not->toBeNull();
    expect($res->json('data.state'))->toBe('allowed');
    expect($res->json('data.status'))->toBe('live');
    expect($res->json('data.viewers_now'))->toBe(1);
    expect($res->json('data.heartbeat_interval'))->toBe(BroadcastPresence::interval());
    // الهوية مربوطة في الرمز كزائر.
    expect(preTokenPayload($res->json('data.token')))->toContain(':guest:');
});

it('binds an authenticated viewer identity and dedups their tabs', function (): void {
    $user = User::factory()->create();
    $b = preBroadcast('live');

    $first = $this->actingAs($user)->postJson(preUrl($b, '/join'))->assertOk();
    expect(preTokenPayload($first->json('data.token')))->toContain(':u'.$user->id.':auth:');
    expect($first->json('data.viewers_now'))->toBe(1);

    // «تبويب آخر» لنفس المستخدم ⇒ نفس العضو ⇒ لا تضخيم.
    $second = $this->actingAs($user)->postJson(preUrl($b, '/join'))->assertOk();
    expect($second->json('data.viewers_now'))->toBe(1);
});

// ─── Heartbeat + counting semantics ──────────────────────────────────────────

it('keeps a guest present across heartbeats without double counting in a bucket', function (): void {
    $b = preBroadcast('live');
    $token = $this->withHeaders(['X-Client-Id' => 'hb'])->postJson(preUrl($b, '/join'))->json('data.token');

    $res = $this->withHeaders(['X-Client-Id' => 'hb'])
        ->postJson(preUrl($b, '/heartbeat'), ['token' => $token])->assertOk();

    expect($res->json('data.state'))->toBe('allowed');
    expect($res->json('data.viewers_now'))->toBe(1); // نفس العضو، نفس الدلو
});

it('counts distinct guests and dedups the same guest (reconnect-safe)', function (): void {
    $b = preBroadcast('live');

    $this->withHeaders(['X-Client-Id' => 'a'])->postJson(preUrl($b, '/join'))->assertOk();
    $two = $this->withHeaders(['X-Client-Id' => 'b'])->postJson(preUrl($b, '/join'))->assertOk();
    expect($two->json('data.viewers_now'))->toBe(2);

    // نفس الزائر ينضمّ ثانيةً (إعادة اتصال/رمز جديد) ⇒ لا يُحتسَب مرّتين.
    $again = $this->withHeaders(['X-Client-Id' => 'a'])->postJson(preUrl($b, '/join'))->assertOk();
    expect($again->json('data.viewers_now'))->toBe(2);
});

it('decays presence to zero after the window without heartbeats (no ghost inflation)', function (): void {
    config(['broadcast.presence.heartbeat_interval' => 30]);
    $b = preBroadcast('live');

    $this->withHeaders(['X-Client-Id' => 'decay'])->postJson(preUrl($b, '/join'))->assertOk();
    expect($this->getJson(preUrl($b))->json('data.viewers_now'))->toBe(1);

    $this->travel(65)->seconds(); // > دلوَين بلا نبض ⇒ يسقط من العدّ
    expect($this->getJson(preUrl($b))->json('data.viewers_now'))->toBe(0);
});

it('counts distinct members and suppresses duplicates at scale (direct engine)', function (): void {
    $b = preBroadcast('live');

    for ($i = 1; $i <= 50; $i++) {
        BroadcastPresence::touch($b->id, "m{$i}");
    }
    expect(BroadcastPresence::count($b->id))->toBe(50);

    BroadcastPresence::touch($b->id, 'm1'); // تكرار في نفس الدلو
    expect(BroadcastPresence::count($b->id))->toBe(50);
});

// ─── Cooperative control state ───────────────────────────────────────────────

it('returns a cooperative ended/offline state and does not count viewers', function (string $status): void {
    $b = preBroadcast($status);

    $res = $this->withHeaders(['X-Client-Id' => 'x'])->postJson(preUrl($b, '/join'))->assertOk();

    expect($res->json('data.state'))->toBe($status);
    expect($res->json('data.viewers_now'))->toBe(0);
})->with(['ended', 'offline']);

it('delivers a closed state when the audience is closed (emergency), uncounted', function (): void {
    $b = preBroadcast('live');
    BroadcastPresenceControl::close($b->id);

    $res = $this->withHeaders(['X-Client-Id' => 'x'])->postJson(preUrl($b, '/join'))->assertOk();

    expect($res->json('data.state'))->toBe('closed');
    expect($res->json('data.viewers_now'))->toBe(0);
});

it('bans a specific member while leaving other viewers allowed', function (): void {
    $b = preBroadcast('live');
    BroadcastPresenceControl::ban($b->id, EngagementActor::guest('banned-client')->key());

    $banned = $this->withHeaders(['X-Client-Id' => 'banned-client'])->postJson(preUrl($b, '/join'))->assertOk();
    expect($banned->json('data.state'))->toBe('banned');

    $other = $this->withHeaders(['X-Client-Id' => 'ok-client'])->postJson(preUrl($b, '/join'))->assertOk();
    expect($other->json('data.state'))->toBe('allowed');
});

it('kicks a member temporarily then allows rejoin after the cooldown', function (): void {
    config(['broadcast.presence.kick_ttl' => 60]);
    $b = preBroadcast('live');
    BroadcastPresenceControl::kick($b->id, EngagementActor::guest('kick-client')->key(), 60);

    $kicked = $this->withHeaders(['X-Client-Id' => 'kick-client'])->postJson(preUrl($b, '/join'))->assertOk();
    expect($kicked->json('data.state'))->toBe('kicked');

    $this->travel(65)->seconds(); // انقضى التهدئة
    $back = $this->withHeaders(['X-Client-Id' => 'kick-client'])->postJson(preUrl($b, '/join'))->assertOk();
    expect($back->json('data.state'))->toBe('allowed');
});

// ─── Visibility gate ─────────────────────────────────────────────────────────

it('refuses presence for non-visible broadcasts (draft/archived/non-public)', function (array $attrs): void {
    $b = preBroadcast('live', $attrs);

    $this->withHeaders(['X-Client-Id' => 'x'])->postJson(preUrl($b, '/join'))->assertStatus(404);
    $this->getJson(preUrl($b))->assertStatus(404);
})->with([
    'draft' => [['status' => 'draft']],
    'archived' => [['status' => 'archived']],
    'not public' => [['is_public' => false]],
]);

it('404s presence for a missing broadcast', function (): void {
    $this->getJson('/api/v1/broadcasts/999999/presence')->assertStatus(404);
    $this->postJson('/api/v1/broadcasts/999999/presence/join')->assertStatus(404);
});

// ─── Token security ──────────────────────────────────────────────────────────

it('rejects an invalid or forged heartbeat token', function (): void {
    $b = preBroadcast('live');

    $this->postJson(preUrl($b, '/heartbeat'), ['token' => 'not-a-valid-token'])->assertStatus(422);
});

it('rejects a token bound to a different broadcast', function (): void {
    $a = preBroadcast('live');
    $other = preBroadcast('live');
    $token = $this->withHeaders(['X-Client-Id' => 'x'])->postJson(preUrl($a, '/join'))->json('data.token');

    $this->withHeaders(['X-Client-Id' => 'x'])
        ->postJson(preUrl($other, '/heartbeat'), ['token' => $token])->assertStatus(422);
});

it('rejects an expired token so the client must rejoin', function (): void {
    // الحدّ الأدنى لعمر الرمز 60ث (يتجاوز النبضة دائماً) — نسافر بعده لإثبات الانتهاء.
    config(['broadcast.presence.token_ttl' => 1]);
    $b = preBroadcast('live');
    $token = $this->withHeaders(['X-Client-Id' => 'x'])->postJson(preUrl($b, '/join'))->json('data.token');

    $this->travel(120)->seconds();
    $this->withHeaders(['X-Client-Id' => 'x'])
        ->postJson(preUrl($b, '/heartbeat'), ['token' => $token])->assertStatus(422);
});

// ─── CDN/cache discipline ────────────────────────────────────────────────────

it('serves the count/status read as CDN-cacheable and join/heartbeat as no-store', function (): void {
    $b = preBroadcast('live');
    $token = $this->withHeaders(['X-Client-Id' => 'x'])->postJson(preUrl($b, '/join'))->json('data.token');

    $read = $this->getJson(preUrl($b))->assertOk();
    expect($read->headers->get('Cache-Control'))->toContain('public');
    expect($read->headers->get('Cache-Control'))->toContain('max-age');

    $join = $this->withHeaders(['X-Client-Id' => 'y'])->postJson(preUrl($b, '/join'))->assertOk();
    expect($join->headers->get('Cache-Control'))->toContain('no-store');

    $hb = $this->withHeaders(['X-Client-Id' => 'x'])
        ->postJson(preUrl($b, '/heartbeat'), ['token' => $token])->assertOk();
    expect($hb->headers->get('Cache-Control'))->toContain('no-store');
});

// ─── Abuse guards ────────────────────────────────────────────────────────────

it('rate-limits heartbeat spam per client', function (): void {
    config(['broadcast.presence.heartbeat_rate_limit' => 3]);
    $b = preBroadcast('live');
    $token = $this->withHeaders(['X-Client-Id' => 'rl'])->postJson(preUrl($b, '/join'))->json('data.token');

    for ($i = 0; $i < 3; $i++) {
        $this->withHeaders(['X-Client-Id' => 'rl'])
            ->postJson(preUrl($b, '/heartbeat'), ['token' => $token])->assertOk();
    }
    $this->withHeaders(['X-Client-Id' => 'rl'])
        ->postJson(preUrl($b, '/heartbeat'), ['token' => $token])->assertStatus(429);
});

it('does not count bot heartbeats toward the viewer total', function (): void {
    $b = preBroadcast('live');

    $res = $this->withHeaders(['X-Client-Id' => 'bot', 'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])
        ->postJson(preUrl($b, '/join'))->assertOk();

    expect($res->json('data.state'))->toBe('allowed');
    expect($res->json('data.viewers_now'))->toBe(0); // البوت لا يُحتسَب
});

// ─── Viewer-count snapshot sync (DB) ─────────────────────────────────────────

it('syncs approximate live viewer counts into the DB snapshot via the command', function (): void {
    $b = preBroadcast('live');
    BroadcastPresence::touch($b->id, 'a');
    BroadcastPresence::touch($b->id, 'b');

    $this->artisan('broadcasts:sync-viewer-counts')->assertExitCode(0);

    expect($b->fresh()->viewer_count)->toBe(2);

    $def = collect(SchedulerRegistry::all())->firstWhere('command', 'broadcasts:sync-viewer-counts');
    expect($def)->not->toBeNull();
    expect($def['frequency'])->toBe('everyMinute');
});
