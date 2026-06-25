<?php

declare(strict_types=1);

use App\Actions\Sport\SyncFollowedFixturesAction;
use App\Models\Follow;
use App\Models\SportFixture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** @return array<string,mixed> مباراة 365 مُصغَّرة */
function fakeGame(int $id, int $statusGroup, string $start): array
{
    return [
        'id' => $id,
        'competitionId' => 5930,
        'seasonNum' => 25,
        'startTime' => $start,
        'statusGroup' => $statusGroup, // 2=مجدولة، 4=منتهية
        'homeCompetitor' => ['id' => 5061, 'name' => 'فرنسا'],
        'awayCompetitor' => ['id' => 5102, 'name' => 'السنغال'],
    ];
}

it('syncs scheduled fixtures for a followed competition and skips finished', function (): void {
    Http::fake([
        '*games/fixtures*' => Http::response(['games' => [
            fakeGame(4627862, 2, now()->addDay()->toIso8601String()),
            fakeGame(111, 4, now()->addHours(2)->toIso8601String()), // منتهية ⇒ تُتخطّى
        ]], 200),
    ]);

    $user = User::factory()->create();
    Follow::create(['user_id' => $user->id, 'followable_type' => 'competition', 'followable_id' => 5930]);

    $count = app(SyncFollowedFixturesAction::class)->handle();

    expect($count)->toBe(1);
    $this->assertDatabaseHas('sport_fixtures', [
        'game_id' => 4627862,
        'status' => 'scheduled',
        'competition_id' => 5930,
        'season_num' => 25,
        'home_team_id' => 5061,
        'away_team_id' => 5102,
    ]);
    $this->assertDatabaseMissing('sport_fixtures', ['game_id' => 111]);

    $fixture = SportFixture::where('game_id', 4627862)->first();
    expect($fixture->next_poll_at)->not->toBeNull()
        ->and($fixture->next_poll_at->equalTo($fixture->start_at))->toBeTrue();
});

it('makes no 365 request when there are no follows', function (): void {
    Http::fake();

    $count = app(SyncFollowedFixturesAction::class)->handle();

    expect($count)->toBe(0);
    Http::assertNothingSent();
});
