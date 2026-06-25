<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\BroadcastNotificationSubscription;
use App\Models\Role;
use App\Models\User;
use App\Support\Broadcast\BroadcastPresenceControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedRoles();
});

function dashSuper(): string
{
    $u = User::factory()->create();
    $u->assignRole('super_admin');

    return $u->createToken('admin', ['admin'])->plainTextToken;
}

it('returns the operational command-center dashboard payload', function (): void {
    $token = dashSuper();
    $viewer = User::factory()->create();

    $live = Broadcast::factory()->create(['status' => 'live', 'is_public' => true, 'started_at' => now(), 'viewer_count' => 42]);
    BroadcastPresenceControl::close($live->id);

    $scheduled = Broadcast::factory()->create(['status' => 'scheduled', 'is_public' => true, 'scheduled_at' => now()->addHours(2)]);
    BroadcastNotificationSubscription::create(['user_id' => $viewer->id, 'broadcast_id' => $scheduled->id]);

    Broadcast::factory()->create(['status' => 'failed', 'kind' => 'tv', 'last_health_status' => 'failed', 'last_health_message' => 'http_503', 'last_health_check_at' => now()]);
    Broadcast::factory()->create(['status' => 'live', 'kind' => 'radio', 'started_at' => now()]);
    BroadcastNotificationSubscription::create(['user_id' => $viewer->id, 'broadcast_id' => null]); // global

    $res = $this->withToken($token)->getJson('/api/v1/admin/broadcasts/dashboard')->assertOk();

    expect($res->json('data.status_counts.live'))->toBe(2);
    expect($res->json('data.status_counts.scheduled'))->toBe(1);
    expect($res->json('data.status_counts.failed'))->toBe(1);

    $liveRow = collect($res->json('data.live'))->firstWhere('id', $live->id);
    expect($liveRow['viewer_count'])->toBe(42);
    expect($liveRow['audience_closed'])->toBeTrue();

    $scheduledRow = collect($res->json('data.scheduled_today'))->firstWhere('id', $scheduled->id);
    expect($scheduledRow['reminder_subscribers'])->toBe(1);

    expect(collect($res->json('data.health_alerts'))->pluck('message'))->toContain('http_503');
    expect($res->json('data.audience.closed'))->toContain(['id' => $live->id, 'title' => $live->title]);
    expect($res->json('data.notifications.global_subscribers'))->toBe(1);
    expect($res->json('data.channels.radio.live'))->toBe(1);
    expect($res->json('data.totals.live_viewers'))->toBe(42);
});

it('forbids the dashboard without broadcasts.view permission', function (): void {
    Role::findByName('editor', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $u = User::factory()->create();
    $u->assignRole('editor');
    $token = $u->createToken('admin', ['admin'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/admin/broadcasts/dashboard')->assertStatus(403);
});
