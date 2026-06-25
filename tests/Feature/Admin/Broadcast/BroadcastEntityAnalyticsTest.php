<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\BroadcastNotificationSubscription;
use App\Models\BroadcastViewerSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function baeSuperToken(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

it('returns real per-broadcast analytics (live perf, health, moderation, notifications)', function (): void {
    $token = baeSuperToken();

    $base = now();
    $broadcast = Broadcast::factory()->create([
        'status' => 'ended',
        'peak_viewer_count' => 45,
        'scheduled_at' => $base->copy()->subHours(2),
        'started_at' => $base->copy()->subHours(2)->addSeconds(120), // بدأ متأخّراً 120ث
        'ended_at' => $base->copy()->subHours(2)->addSeconds(120 + 3480),
    ]);

    // عيّنات تزامن: المتوسّط=20، ذروة النافذة=30.
    foreach ([10, 20, 30] as $i => $v) {
        BroadcastViewerSample::create([
            'broadcast_id' => $broadcast->id,
            'viewers' => $v,
            'sampled_at' => $base->copy()->subMinutes(10 - $i),
        ]);
    }

    // فحوصات صحّة (ترتيب زمنيّ): healthy, failed, failed, healthy, healthy ⇒ تعافٍ واحد.
    foreach ([['healthy', 50], ['failed', null], ['failed', null], ['healthy', 70], ['healthy', 60]] as $i => [$status, $latency]) {
        DB::table('broadcast_health_checks')->insert([
            'broadcast_id' => $broadcast->id,
            'status' => $status,
            'latency_ms' => $latency,
            'failure_reason' => $status === 'failed' ? 'timeout' : null,
            'checked_at' => $base->copy()->subMinutes(20 - $i),
        ]);
    }

    // أحداث إشراف دائمة (سجلّ التدقيق).
    foreach (['viewer_banned', 'viewer_kicked', 'viewer_kicked'] as $i => $event) {
        DB::table('activity_log')->insert([
            'log_name' => 'broadcast',
            'description' => $event,
            'subject_type' => $broadcast->getMorphClass(),
            'subject_id' => $broadcast->id,
            'event' => $event,
            'properties' => json_encode(['member' => 'u'.$i, 'reason' => 'spam']),
            'created_at' => $base->copy()->subMinutes(5 - $i),
            'updated_at' => $base->copy()->subMinutes(5 - $i),
        ]);
    }

    // مشتركو تذكير (لهذا البثّ) + مشترك عامّ.
    BroadcastNotificationSubscription::create(['user_id' => User::factory()->create()->id, 'broadcast_id' => $broadcast->id]);
    BroadcastNotificationSubscription::create(['user_id' => User::factory()->create()->id, 'broadcast_id' => null]);

    $res = $this->withToken($token)
        ->getJson("/api/v1/admin/broadcasts/{$broadcast->id}/analytics?range=30d")
        ->assertOk();

    // الأداء الحيّ.
    expect($res->json('data.live_performance.peak_all_time'))->toBe(45);
    expect($res->json('data.live_performance.peak_in_window'))->toBe(30);
    expect($res->json('data.live_performance.average_concurrent'))->toBe(20);
    expect($res->json('data.live_performance.sample_count'))->toBe(3);
    expect($res->json('data.live_performance.unique_viewers.available'))->toBeFalse();

    // منحنى التزامن.
    expect($res->json('data.concurrency.points'))->toHaveCount(3);

    // الخطّ الزمني: تأخير البدء + المدّة (محسوبان من الطوابع المخزّنة).
    expect($res->json('data.timeline.start_delay_seconds'))->toBe(120);
    expect($res->json('data.timeline.duration_seconds'))->toBe(3480);

    // الصحّة.
    expect($res->json('data.health.failure_count'))->toBe(2);
    expect($res->json('data.health.healthy_count'))->toBe(3);
    expect($res->json('data.health.recovery_count'))->toBe(1);
    expect($res->json('data.health.recent_events'))->toBeArray();

    // الإشراف.
    expect($res->json('data.moderation.bans'))->toBe(1);
    expect($res->json('data.moderation.kicks'))->toBe(2);

    // الإشعارات (التسليم مؤجّل بصدق).
    expect($res->json('data.notifications.reminder_subscribers'))->toBe(1);
    expect($res->json('data.notifications.delivery.available'))->toBeFalse();
});

it('keeps current viewers fresh and zero for a non-live broadcast', function (): void {
    $token = baeSuperToken();
    $broadcast = Broadcast::factory()->create(['status' => 'ended']);

    $res = $this->withToken($token)
        ->getJson("/api/v1/admin/broadcasts/{$broadcast->id}/analytics")
        ->assertOk();

    expect($res->json('data.live_performance.current_viewers'))->toBe(0);
});

it('requires broadcasts.view to read per-broadcast analytics', function (): void {
    $broadcast = Broadcast::factory()->create();
    $token = User::factory()->create()->createToken('admin', ['admin'])->plainTextToken; // بلا أدوار/صلاحيات

    $this->withToken($token)->getJson("/api/v1/admin/broadcasts/{$broadcast->id}/analytics")->assertStatus(403);
});
