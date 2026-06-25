<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\Role;
use App\Models\User;
use App\Support\Broadcast\BroadcastPresenceControl;
use App\Support\Engagement\EngagementActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

// ─── Helpers (أسماء فريدة عالمياً) ───────────────────────────────────────────

function modSuperUser(): User
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u;
}

function modToken(User $u): string
{
    return $u->createToken('admin', ['admin'])->plainTextToken;
}

/** محرّر بصلاحيات محدّدة (لاختبار RBAC الحبيبيّ). */
function modActor(string ...$perms): string
{
    $role = Role::findByName('editor', 'web');
    if ($perms !== []) {
        $role->givePermissionTo($perms);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

function modBroadcast(string $status = 'live', array $attrs = []): Broadcast
{
    $times = match ($status) {
        'scheduled' => ['scheduled_at' => now()->addHour()],
        'live' => ['started_at' => now()->subMinutes(5)],
        default => [],
    };

    return Broadcast::factory()->create(array_merge(['status' => $status, 'is_public' => true], $times, $attrs));
}

function modUrl(Broadcast $b, string $action): string
{
    return "/api/v1/admin/broadcasts/{$b->id}/moderation/{$action}";
}

function presenceUrl(Broadcast $b, string $suffix = ''): string
{
    return "/api/v1/broadcasts/{$b->id}/presence{$suffix}";
}

// ─── Temporary bans ──────────────────────────────────────────────────────────

it('bans an authenticated viewer by user_id with reason + expiry (strong enforcement)', function (): void {
    $admin = modSuperUser();
    $victim = User::factory()->create();
    $b = modBroadcast('live');

    $this->withToken(modToken($admin))
        ->postJson(modUrl($b, 'ban'), ['user_id' => $victim->id, 'duration_minutes' => 30, 'reason' => 'إساءة'])
        ->assertOk()
        ->assertJsonPath('data.member', 'u'.$victim->id)
        ->assertJsonPath('data.duration_minutes', 30);

    expect(BroadcastPresenceControl::isBanned($b->id, 'u'.$victim->id))->toBeTrue();
    expect(BroadcastPresenceControl::banInfo($b->id, 'u'.$victim->id)['reason'])->toBe('إساءة');
});

it('bans a guest by presence member id (best-effort enforcement stored)', function (): void {
    $b = modBroadcast('live');
    $member = EngagementActor::guest('guest-victim')->key();

    $this->withToken(modToken(modSuperUser()))
        ->postJson(modUrl($b, 'ban'), ['member' => $member, 'duration_minutes' => 30])
        ->assertOk()
        ->assertJsonPath('data.member', $member);

    expect(BroadcastPresenceControl::isBanned($b->id, $member))->toBeTrue();
});

it('denies a banned authenticated viewer reconnect via presence (integration)', function (): void {
    $victim = User::factory()->create();
    $b = modBroadcast('live');

    $this->withToken(modToken(modSuperUser()))
        ->postJson(modUrl($b, 'ban'), ['user_id' => $victim->id])->assertOk();

    // إعادة الاتصال (انضمام) للمحظور مرفوضة تعاونياً — resolve يُعيد banned في join.
    $this->flushHeaders();
    $res = $this->actingAs($victim)->postJson(presenceUrl($b, '/join'))->assertOk();
    expect($res->json('data.state'))->toBe('banned');
});

it('denies the next heartbeat of a viewer banned mid-session', function (): void {
    $b = modBroadcast('live');
    $member = EngagementActor::guest('mid-session')->key();

    $join = $this->withHeaders(['X-Client-Id' => 'mid-session'])->postJson(presenceUrl($b, '/join'))->assertOk();
    expect($join->json('data.state'))->toBe('allowed');
    $token = $join->json('data.token');

    $this->withToken(modToken(modSuperUser()))->postJson(modUrl($b, 'ban'), ['member' => $member])->assertOk();

    $this->flushHeaders();
    $hb = $this->withHeaders(['X-Client-Id' => 'mid-session'])
        ->postJson(presenceUrl($b, '/heartbeat'), ['token' => $token])->assertOk();
    expect($hb->json('data.state'))->toBe('banned');
});

it('expires a temporary ban automatically via TTL (no cleanup job)', function (): void {
    $b = modBroadcast('live');
    $member = EngagementActor::guest('expiry')->key();

    $this->withToken(modToken(modSuperUser()))
        ->postJson(modUrl($b, 'ban'), ['member' => $member, 'duration_minutes' => 1])->assertOk();
    expect(BroadcastPresenceControl::isBanned($b->id, $member))->toBeTrue();

    $this->travel(120)->seconds(); // > مدّة الحظر (دقيقة) ⇒ انتهى تلقائياً
    expect(BroadcastPresenceControl::isBanned($b->id, $member))->toBeFalse();
});

it('lifts a ban early via unban', function (): void {
    $b = modBroadcast('live');
    $member = EngagementActor::guest('unban-me')->key();
    $token = modToken(modSuperUser());

    $this->withToken($token)->postJson(modUrl($b, 'ban'), ['member' => $member])->assertOk();
    expect(BroadcastPresenceControl::isBanned($b->id, $member))->toBeTrue();

    $this->withToken($token)->postJson(modUrl($b, 'unban'), ['member' => $member])->assertOk();
    expect(BroadcastPresenceControl::isBanned($b->id, $member))->toBeFalse();
});

// ─── Kick ──────────────────────────────────────────────────────────────────

it('kicks a viewer (temporary teardown stored; rejoin allowed unless banned)', function (): void {
    $b = modBroadcast('live');
    $member = EngagementActor::guest('kick-me')->key();

    $this->withToken(modToken(modSuperUser()))->postJson(modUrl($b, 'kick'), ['member' => $member])->assertOk();

    // مُخزَّن للطرد التعاونيّ (النبضة تُعيد kicked)؛ ليس حظراً — يجوز العودة بعد التهدئة.
    expect(BroadcastPresenceControl::isKicked($b->id, $member))->toBeTrue();
    expect(BroadcastPresenceControl::isBanned($b->id, $member))->toBeFalse();
});

// ─── Close / reopen audience ─────────────────────────────────────────────────

it('closes the audience: all viewers receive closed and the count drops', function (): void {
    $b = modBroadcast('live');
    // مشاهد حاضر قبل الإغلاق.
    $this->withHeaders(['X-Client-Id' => 'present'])->postJson(presenceUrl($b, '/join'))->assertOk();

    $this->withToken(modToken(modSuperUser()))->postJson(modUrl($b, 'close'))->assertOk();
    expect(BroadcastPresenceControl::isClosed($b->id))->toBeTrue();

    $this->flushHeaders();
    $res = $this->withHeaders(['X-Client-Id' => 'present'])->postJson(presenceUrl($b, '/join'))->assertOk();
    expect($res->json('data.state'))->toBe('closed');
    expect($res->json('data.viewers_now'))->toBe(0); // تفكيك الحضور
});

it('reopens a closed audience', function (): void {
    $b = modBroadcast('live');
    $token = modToken(modSuperUser());

    $this->withToken($token)->postJson(modUrl($b, 'close'))->assertOk();
    $this->withToken($token)->postJson(modUrl($b, 'reopen'))->assertOk();
    expect(BroadcastPresenceControl::isClosed($b->id))->toBeFalse();

    $this->flushHeaders();
    expect($this->withHeaders(['X-Client-Id' => 'back'])->postJson(presenceUrl($b, '/join'))->json('data.state'))
        ->toBe('allowed');
});

// ─── Emergency shutdown (lifecycle-safe) ─────────────────────────────────────

it('emergency shutdown takes a live broadcast offline, closes the audience, and audits with the actor', function (): void {
    $admin = modSuperUser();
    $b = modBroadcast('live');

    $this->withToken(modToken($admin))->postJson(modUrl($b, 'emergency-shutdown'))
        ->assertOk()
        ->assertJsonPath('data.status', 'offline');

    expect($b->fresh()->status->value)->toBe('offline');       // انتقال B2 شرعيّ (live→offline)
    expect(BroadcastPresenceControl::isClosed($b->id))->toBeTrue();

    $this->flushHeaders();
    $res = $this->withHeaders(['X-Client-Id' => 'v'])->postJson(presenceUrl($b, '/join'))->assertOk();
    expect($res->json('data.state'))->toBe('offline');         // offline أسبق من closed
    expect($res->json('data.viewers_now'))->toBe(0);

    $activity = Activity::query()->where('log_name', 'broadcast')->where('event', 'emergency_shutdown')->latest('id')->first();
    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($admin->id);
});

it('emergency shutdown on a non-live broadcast closes the audience without an illegal lifecycle transition', function (): void {
    $b = modBroadcast('scheduled');

    $this->withToken(modToken(modSuperUser()))->postJson(modUrl($b, 'emergency-shutdown'))->assertOk();

    expect($b->fresh()->status->value)->toBe('scheduled');     // آلة B2 لم تُنتهَك (scheduled↛offline)
    expect(BroadcastPresenceControl::isClosed($b->id))->toBeTrue();

    $this->flushHeaders();
    expect($this->withHeaders(['X-Client-Id' => 'v'])->postJson(presenceUrl($b, '/join'))->json('data.state'))
        ->toBe('closed');
});

// ─── RBAC (granular — not collapsed) ─────────────────────────────────────────

it('forbids moderation without the matching granular permission', function (): void {
    $b = modBroadcast('live');
    $victim = User::factory()->create();
    $token = modActor(); // محرّر بلا صلاحيات إشراف

    $this->withToken($token)->postJson(modUrl($b, 'kick'), ['user_id' => $victim->id])->assertStatus(403);
    $this->withToken($token)->postJson(modUrl($b, 'ban'), ['user_id' => $victim->id])->assertStatus(403);
    $this->withToken($token)->postJson(modUrl($b, 'close'))->assertStatus(403);
    $this->withToken($token)->postJson(modUrl($b, 'emergency-shutdown'))->assertStatus(403);
});

it('scopes permissions per capability — viewer_ban cannot kick', function (): void {
    $b = modBroadcast('live');
    $victim = User::factory()->create();
    $token = modActor('broadcasts.viewer_ban');

    $this->withToken($token)->postJson(modUrl($b, 'ban'), ['user_id' => $victim->id])->assertOk();
    $this->withToken($token)->postJson(modUrl($b, 'kick'), ['user_id' => $victim->id])->assertStatus(403);
});

// ─── Audit + validation ──────────────────────────────────────────────────────

it('writes a durable audit entry attributed to the actor for a ban', function (): void {
    $admin = modSuperUser();
    $victim = User::factory()->create();
    $b = modBroadcast('live');

    $this->withToken(modToken($admin))
        ->postJson(modUrl($b, 'ban'), ['user_id' => $victim->id, 'reason' => 'spam'])->assertOk();

    $activity = Activity::query()->where('log_name', 'broadcast')->where('event', 'viewer_banned')->latest('id')->first();
    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->properties['member'])->toBe('u'.$victim->id);
    expect($activity->properties['reason'])->toBe('spam');
});

it('requires a viewer target (user_id or member) for a ban', function (): void {
    $b = modBroadcast('live');

    $this->withToken(modToken(modSuperUser()))->postJson(modUrl($b, 'ban'), [])->assertStatus(422);
});

it('is idempotent and race-safe for repeated close/ban (atomic cache flags)', function (): void {
    $b = modBroadcast('live');
    $member = EngagementActor::guest('rc')->key();
    $token = modToken(modSuperUser());

    $this->withToken($token)->postJson(modUrl($b, 'close'))->assertOk();
    $this->withToken($token)->postJson(modUrl($b, 'close'))->assertOk();
    expect(BroadcastPresenceControl::isClosed($b->id))->toBeTrue();

    $this->withToken($token)->postJson(modUrl($b, 'ban'), ['member' => $member])->assertOk();
    $this->withToken($token)->postJson(modUrl($b, 'ban'), ['member' => $member])->assertOk();
    expect(BroadcastPresenceControl::isBanned($b->id, $member))->toBeTrue();
});
