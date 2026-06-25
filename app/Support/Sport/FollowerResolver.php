<?php

declare(strict_types=1);

namespace App\Support\Sport;

use App\Enums\FollowableType;
use App\Models\Follow;
use App\Models\SportFixture;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * يحلّ متابِعي مباراةٍ إلى مستخدمين مميَّزين — مشترك بين تذكير ما قبل المباراة (الكتلة B) والأحداث المباشرة
 * (الكتلة C) تفاديًا لتكرار المنطق. المسارات: متابعة المباراة نفسها · بطولتها · أحد فريقيها · أو لاعبٍ في أحد
 * فريقيها (تحويل لاعب→فريق مُكاش 12h). تُبنى خريطة اللاعبين مرّةً للدفعة ثمّ تُمرَّر لكلّ مباراة.
 */
final class FollowerResolver
{
    public function __construct(private readonly Sport365Client $client) {}

    /**
     * خريطة team_id → معرّفات المستخدمين المتابعين لِلاعبٍ في ذلك الفريق (مرّةً للدفعة).
     *
     * @return array<int,array<int,int>>
     */
    public function playerFollowersByTeam(): array
    {
        $map = [];
        $rows = Follow::query()->where('followable_type', FollowableType::Player->value)->get(['user_id', 'followable_id']);

        foreach ($rows as $row) {
            $playerId = (int) $row->followable_id;
            $teamIds = Cache::remember(
                "follow:player-teams:{$playerId}",
                now()->addHours(12),
                fn (): array => $this->client->teamIdsForPlayer($playerId),
            );
            foreach ($teamIds as $teamId) {
                $map[$teamId][] = (int) $row->user_id;
            }
        }

        return $map;
    }

    /**
     * متابِعو مباراةٍ (مميَّزون): المباراة/البطولة/الفريق مباشرةً + متابِعو لاعبٍ في أحد الفريقين.
     *
     * @param  array<int,array<int,int>>  $playerFollowersByTeam
     * @return array<int,int>
     */
    public function followersOfFixture(SportFixture $fixture, array $playerFollowersByTeam): array
    {
        $userIds = $this->directFollowers($fixture);

        foreach ([$fixture->home_team_id, $fixture->away_team_id] as $teamId) {
            if ($teamId !== null && isset($playerFollowersByTeam[$teamId])) {
                $userIds = array_merge($userIds, $playerFollowersByTeam[$teamId]);
            }
        }

        return array_values(array_unique($userIds));
    }

    /** @return array<int,int> */
    private function directFollowers(SportFixture $fixture): array
    {
        $teamIds = array_values(array_filter([$fixture->home_team_id, $fixture->away_team_id], static fn ($v): bool => $v !== null));

        return Follow::query()
            ->where(function (Builder $q) use ($fixture, $teamIds): void {
                $q->where(fn (Builder $w) => $w->where('followable_type', FollowableType::Game->value)->where('followable_id', $fixture->game_id))
                    ->orWhere(fn (Builder $w) => $w->where('followable_type', FollowableType::Competition->value)->where('followable_id', $fixture->competition_id))
                    ->orWhere(fn (Builder $w) => $w->where('followable_type', FollowableType::Team->value)->whereIn('followable_id', $teamIds === [] ? [0] : $teamIds));
            })
            ->distinct()
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
