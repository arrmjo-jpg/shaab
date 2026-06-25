<?php

declare(strict_types=1);

use App\Actions\Sport\PollLiveFollowedMatchesAction;
use App\Models\Follow;
use App\Models\FollowNotification;
use App\Models\SportFixture;
use App\Models\User;
use App\Notifications\MatchEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

/** حدث 365 مُصغَّر */
function pollEv(int $typeId, int $subTypeId, int $minute, int $playerId): array
{
    return [
        'competitorId' => 5061,
        'gameTime' => $minute,
        'addedTime' => 0,
        'gameTimeDisplay' => "{$minute}'",
        'playerId' => $playerId,
        'isMajor' => true,
        'eventType' => ['id' => $typeId, 'subTypeId' => $subTypeId, 'name' => 'حدث', 'subTypeName' => 'حدث'],
    ];
}

/** استجابة `game/?gameId=` مُصغَّرة (statusGroup: 3=حيّة، 4=منتهية) */
function pollGameJson(array $events, int $statusGroup = 3): array
{
    return ['game' => [
        'id' => 4627876,
        'statusGroup' => $statusGroup,
        'homeCompetitor' => ['id' => 5061, 'name' => 'فرنسا'],
        'awayCompetitor' => ['id' => 5102, 'name' => 'السنغال'],
        'members' => [['id' => 3359858, 'shortName' => 'العمري'], ['id' => 999, 'shortName' => 'لاعب']],
        'events' => $events,
    ]];
}

function liveFixture(): SportFixture
{
    return SportFixture::create([
        'game_id' => 4627876,
        'competition_id' => 5930,
        'season_num' => 25,
        'home_team_id' => 5061,
        'home_name' => 'فرنسا',
        'away_team_id' => 5102,
        'away_name' => 'السنغال',
        'status' => 'live',
        'start_at' => now()->subMinutes(50),
        'next_poll_at' => now()->subSecond(), // مستحقّة للاستطلاع
    ]);
}

it('emits no duplicate notifications when event order changes across two polls', function (): void {
    $goal = pollEv(1, 1, 41, 3359858);
    $yellow = pollEv(2, 0, 44, 999);

    Http::fake(['*game/*' => Http::sequence()
        ->push(pollGameJson([$goal, $yellow]))   // الاستطلاع 1: ترتيب A,B
        ->push(pollGameJson([$yellow, $goal])),   // الاستطلاع 2: نفس الأحداث معكوسة
    ]);

    liveFixture();
    $user = User::factory()->create();
    Follow::create(['user_id' => $user->id, 'followable_type' => 'competition', 'followable_id' => 5930]);

    // الاستطلاع 1 ⇒ إشعاران (حدثان مؤهَّلان).
    expect(app(PollLiveFollowedMatchesAction::class)->handle())->toBe(2);

    // أعِد جعلها مستحقّة (الاستطلاع 1 ضبط next_poll_at=+45ث).
    SportFixture::where('game_id', 4627876)->update(['next_poll_at' => now()->subSecond()]);

    // الاستطلاع 2 (ترتيب مختلف) ⇒ صفر إشعارات جديدة.
    expect(app(PollLiveFollowedMatchesAction::class)->handle())->toBe(0);

    expect(FollowNotification::where('kind', 'event')->count())->toBe(2);
    Notification::assertSentToTimes($user, MatchEventNotification::class, 2);
});

it('notifies a follower of a goal once and ignores substitutions', function (): void {
    Http::fake(['*game/*' => Http::response(pollGameJson([
        pollEv(1, 1, 41, 3359858), // هدف ⇒ يُشعَر
        pollEv(1000, 0, 60, 999),  // تبديل ⇒ يُتجاهَل
    ]))]);

    liveFixture();
    $user = User::factory()->create();
    Follow::create(['user_id' => $user->id, 'followable_type' => 'team', 'followable_id' => 5061]);

    expect(app(PollLiveFollowedMatchesAction::class)->handle())->toBe(1);
    Notification::assertSentToTimes($user, MatchEventNotification::class, 1);
});

it('stops polling a finished match immediately (next_poll_at null)', function (): void {
    Http::fake(['*game/*' => Http::response(pollGameJson([], 4))]); // 4 = منتهية

    liveFixture();
    $user = User::factory()->create();
    Follow::create(['user_id' => $user->id, 'followable_type' => 'competition', 'followable_id' => 5930]);

    app(PollLiveFollowedMatchesAction::class)->handle();

    $fixture = SportFixture::where('game_id', 4627876)->first();
    expect($fixture->status)->toBe('finished')
        ->and($fixture->next_poll_at)->toBeNull();
});
