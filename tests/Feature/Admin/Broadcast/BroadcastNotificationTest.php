<?php

declare(strict_types=1);

use App\Actions\Admin\Broadcast\DispatchBroadcastRemindersAction;
use App\Actions\Admin\Broadcast\StartBroadcastAction;
use App\Jobs\SendBroadcastNotificationJob;
use App\Models\Broadcast;
use App\Models\BroadcastNotificationSubscription;
use App\Models\User;
use App\Support\Broadcast\BroadcastPushGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

// ─── Helpers (أسماء فريدة عالمياً) ───────────────────────────────────────────

/** @return array{0:User,1:string} */
function bnUser(): array
{
    $u = User::factory()->create();
    $u->assignRole('user');

    return [$u, $u->createToken('public', ['user'])->plainTextToken];
}

function bnBroadcast(string $status = 'scheduled', array $attrs = []): Broadcast
{
    $times = match ($status) {
        'scheduled' => ['scheduled_at' => now()->addHour()],
        'live' => ['started_at' => now()->subMinute()],
        default => [],
    };

    return Broadcast::factory()->create(array_merge(['status' => $status, 'is_public' => true], $times, $attrs));
}

// ─── Subscription API (auth-only, idempotent) ────────────────────────────────

it('denies guests from managing notification preferences', function (): void {
    $b = bnBroadcast();

    $this->postJson('/api/v1/broadcasts/notifications/live')->assertStatus(401);
    $this->postJson("/api/v1/broadcasts/{$b->id}/reminder")->assertStatus(401);
});

it('opts a user into global live notifications (idempotent)', function (): void {
    [$user, $token] = bnUser();

    $this->withToken($token)->postJson('/api/v1/broadcasts/notifications/live')
        ->assertOk()->assertJsonPath('data.subscribed', true);
    $this->withToken($token)->postJson('/api/v1/broadcasts/notifications/live')->assertOk(); // مكرّر

    expect(BroadcastNotificationSubscription::query()->forUser($user->id)->global()->count())->toBe(1);
    expect($this->withToken($token)->getJson('/api/v1/broadcasts/notifications/live')->json('data.subscribed'))->toBeTrue();
});

it('opts a user out of global live notifications', function (): void {
    [$user, $token] = bnUser();
    $this->withToken($token)->postJson('/api/v1/broadcasts/notifications/live')->assertOk();

    $this->withToken($token)->deleteJson('/api/v1/broadcasts/notifications/live')
        ->assertOk()->assertJsonPath('data.subscribed', false);

    expect(BroadcastNotificationSubscription::query()->forUser($user->id)->global()->exists())->toBeFalse();
});

it('subscribes/unsubscribes a per-event reminder (idempotent)', function (): void {
    [$user, $token] = bnUser();
    $b = bnBroadcast('scheduled');

    $this->withToken($token)->postJson("/api/v1/broadcasts/{$b->id}/reminder")
        ->assertOk()->assertJsonPath('data.subscribed', true);
    $this->withToken($token)->postJson("/api/v1/broadcasts/{$b->id}/reminder")->assertOk(); // مكرّر
    expect(BroadcastNotificationSubscription::query()->forUser($user->id)->forBroadcast($b->id)->count())->toBe(1);

    $this->withToken($token)->deleteJson("/api/v1/broadcasts/{$b->id}/reminder")
        ->assertOk()->assertJsonPath('data.subscribed', false);
    expect(BroadcastNotificationSubscription::query()->forUser($user->id)->forBroadcast($b->id)->exists())->toBeFalse();
});

it('refuses reminder subscription for non-visible broadcasts', function (): void {
    [, $token] = bnUser();
    $b = bnBroadcast('scheduled', ['status' => 'draft']);

    $this->withToken($token)->postJson("/api/v1/broadcasts/{$b->id}/reminder")->assertStatus(404);
});

// ─── Live notification dispatch + anti-flapping ──────────────────────────────

it('dispatches a live notification once when a public broadcast goes live', function (): void {
    Queue::fake();
    $b = bnBroadcast('scheduled');

    (new StartBroadcastAction)->handle($b);

    Queue::assertPushed(SendBroadcastNotificationJob::class, 1);
    Queue::assertPushed(SendBroadcastNotificationJob::class, fn ($job): bool => $job->type === 'live' && $job->broadcastId === $b->id);
    expect($b->fresh()->live_notified_at)->not->toBeNull();
});

it('does NOT dispatch a live notification for a non-public broadcast', function (): void {
    Queue::fake();
    $b = bnBroadcast('scheduled', ['is_public' => false]);

    (new StartBroadcastAction)->handle($b);

    Queue::assertNotPushed(SendBroadcastNotificationJob::class);
});

it('suppresses repeated live notifications under health flapping (anti-flap)', function (): void {
    Queue::fake();
    $b = bnBroadcast('scheduled');

    (new StartBroadcastAction)->handle($b);          // live ⇒ إشعار واحد
    $b->fresh()->update(['status' => 'failed']);     // عطل صحّي
    $b->fresh()->update(['status' => 'live']);       // استرجاع (ارتعاش)
    $b->fresh()->update(['status' => 'offline']);
    $b->fresh()->update(['status' => 'live']);       // استئناف

    Queue::assertPushed(SendBroadcastNotificationJob::class, 1); // مرّة واحدة فقط رغم التذبذب
});

// ─── Scheduled reminder dispatch ─────────────────────────────────────────────

it('dispatches a reminder for a subscribed broadcast entering the window (deduped)', function (): void {
    Queue::fake();
    [$user] = bnUser();
    $b = bnBroadcast('scheduled', ['scheduled_at' => now()->addMinutes(20)]); // ضمن نافذة 30د
    BroadcastNotificationSubscription::create(['user_id' => $user->id, 'broadcast_id' => $b->id]);

    expect((new DispatchBroadcastRemindersAction)->handle())->toBe(1);
    Queue::assertPushed(SendBroadcastNotificationJob::class, fn ($job): bool => $job->type === 'reminder' && $job->broadcastId === $b->id);
    expect($b->fresh()->reminder_dispatched_at)->not->toBeNull();

    // تشغيل ثانٍ ⇒ لا تكرار (العلامة مضبوطة).
    expect((new DispatchBroadcastRemindersAction)->handle())->toBe(0);
    Queue::assertPushed(SendBroadcastNotificationJob::class, 1);
});

it('does not dispatch reminders for broadcasts without subscribers or outside the window', function (): void {
    Queue::fake();
    [$user] = bnUser();

    bnBroadcast('scheduled', ['scheduled_at' => now()->addMinutes(20)]);                 // داخل النافذة، بلا مشترك
    $far = bnBroadcast('scheduled', ['scheduled_at' => now()->addHours(3)]);              // خارج النافذة
    BroadcastNotificationSubscription::create(['user_id' => $user->id, 'broadcast_id' => $far->id]);

    expect((new DispatchBroadcastRemindersAction)->handle())->toBe(0);
    Queue::assertNotPushed(SendBroadcastNotificationJob::class);
});

it('does not dispatch a reminder for a cancelled (archived) broadcast', function (): void {
    Queue::fake();
    [$user] = bnUser();
    $b = bnBroadcast('scheduled', ['scheduled_at' => now()->addMinutes(20)]);
    BroadcastNotificationSubscription::create(['user_id' => $user->id, 'broadcast_id' => $b->id]);

    $b->fresh()->update(['status' => 'archived']); // أُلغي

    expect((new DispatchBroadcastRemindersAction)->handle())->toBe(0);
    Queue::assertNotPushed(SendBroadcastNotificationJob::class);
});

it('resets the reminder marker when the schedule changes (re-dispatch at new time)', function (): void {
    $b = bnBroadcast('scheduled');
    $b->update(['reminder_dispatched_at' => now()]);
    expect($b->fresh()->reminder_dispatched_at)->not->toBeNull();

    $b->update(['scheduled_at' => now()->addHours(3)]); // تغيّر الموعد ⇒ المراقب يصفّر العلامة

    expect($b->fresh()->reminder_dispatched_at)->toBeNull();
});

// ─── Job self-validation + retry idempotency ─────────────────────────────────

it('publishes nothing when the broadcast is no longer valid at run time', function (): void {
    $b = bnBroadcast('scheduled'); // وظيفة live لبثّ غير مباشر ⇒ لا نشر
    $gateway = $this->mock(BroadcastPushGateway::class);
    $gateway->shouldReceive('publish')->never();

    (new SendBroadcastNotificationJob('live', $b->id))->handle($gateway);
});

it('is retry-idempotent — a re-run of the same job does not re-publish', function (): void {
    $b = bnBroadcast('live');
    $gateway = $this->mock(BroadcastPushGateway::class);
    // live ينشر لموضوعين (عام + الحدث) في التشغيل الأول فقط؛ إعادة التشغيل لا تنشر.
    $gateway->shouldReceive('publish')->twice();

    (new SendBroadcastNotificationJob('live', $b->id))->handle($gateway);
    (new SendBroadcastNotificationJob('live', $b->id))->handle($gateway); // إعادة محاولة
});
