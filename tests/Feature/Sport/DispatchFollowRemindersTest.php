<?php

declare(strict_types=1);

use App\Actions\Sport\DispatchFollowRemindersAction;
use App\Models\Follow;
use App\Models\FollowNotification;
use App\Models\SportFixture;
use App\Models\User;
use App\Notifications\MatchReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    Http::fake(); // لا نداء 365 حقيقيّ (لا متابعات لاعبين في هذه الاختبارات)
});

function reminderFixture(array $attrs = []): SportFixture
{
    return SportFixture::create(array_merge([
        'game_id' => 4627862,
        'competition_id' => 5930,
        'season_num' => 25,
        'home_team_id' => 5061,
        'home_name' => 'فرنسا',
        'away_team_id' => 5102,
        'away_name' => 'السنغال',
        'status' => 'scheduled',
        'start_at' => now()->addMinutes(15),
        'next_poll_at' => now()->addMinutes(15),
    ], $attrs));
}

it('notifies a competition follower once for a match in the reminder window', function (): void {
    reminderFixture();
    $user = User::factory()->create();
    Follow::create(['user_id' => $user->id, 'followable_type' => 'competition', 'followable_id' => 5930]);

    $sent = app(DispatchFollowRemindersAction::class)->handle();

    expect($sent)->toBe(1);
    $this->assertDatabaseHas('follow_notifications', [
        'user_id' => $user->id,
        'game_id' => 4627862,
        'kind' => 'reminder',
        'dedup_key' => 'reminder:4627862',
    ]);
    Notification::assertSentTo($user, MatchReminderNotification::class);

    // إعادة التشغيل ⇒ لا تكرار.
    expect(app(DispatchFollowRemindersAction::class)->handle())->toBe(0);
    expect(FollowNotification::where('user_id', $user->id)->count())->toBe(1);
});

it('does not notify for a match outside the reminder window', function (): void {
    reminderFixture(['game_id' => 999, 'start_at' => now()->addHours(5), 'next_poll_at' => now()->addHours(5)]);
    $user = User::factory()->create();
    Follow::create(['user_id' => $user->id, 'followable_type' => 'competition', 'followable_id' => 5930]);

    expect(app(DispatchFollowRemindersAction::class)->handle())->toBe(0);
    Notification::assertNothingSent();
});

it('aggregates a user following via team and competition into a single reminder', function (): void {
    reminderFixture();
    $user = User::factory()->create();
    Follow::create(['user_id' => $user->id, 'followable_type' => 'competition', 'followable_id' => 5930]);
    Follow::create(['user_id' => $user->id, 'followable_type' => 'team', 'followable_id' => 5061]);

    expect(app(DispatchFollowRemindersAction::class)->handle())->toBe(1);
    expect(FollowNotification::where('user_id', $user->id)->count())->toBe(1);
    Notification::assertSentToTimes($user, MatchReminderNotification::class, 1);
});
