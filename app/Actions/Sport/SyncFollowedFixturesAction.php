<?php

declare(strict_types=1);

namespace App\Actions\Sport;

use App\Enums\FollowableType;
use App\Models\Follow;
use App\Models\SportFixture;
use App\Support\Sport\Sport365Client;
use Illuminate\Support\Facades\Cache;

/**
 * يزامن مرآة `sport_fixtures` للكيانات المتابَعة فقط (تفادي مزامنة كلّ 365): بطولات (كلّ مبارياتها) + فِرَق +
 * لاعبون (→مواعيد فِرَقهم) + مباريات بعينها. يتخطّى المنتهية، ويهيّئ `next_poll_at=start_at` (كادنس الاستطلاع)،
 * ويقلّم المواعيد القديمة. idempotent (updateOrCreate بـgame_id) + قفل موزّع يمنع التداخل. يُدار بـSchedulerRegistry.
 *
 * @return int عدد المواعيد المُزامَنة
 */
final class SyncFollowedFixturesAction
{
    private const LOCK_KEY = 'follows:sync-fixtures';

    public function __construct(private readonly Sport365Client $client) {}

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, 280);
        if (! $lock->get()) {
            return 0; // تشغيل آخر جارٍ — تخطٍّ آمن
        }

        try {
            $compIds = $this->followedIds(FollowableType::Competition);
            $teamIds = $this->followedIds(FollowableType::Team);
            $playerIds = $this->followedIds(FollowableType::Player);
            $matchIds = $this->followedIds(FollowableType::Game);

            // لا متابعات ⇒ لا نداءات 365 إطلاقًا.
            if ($compIds === [] && $teamIds === [] && $playerIds === [] && $matchIds === []) {
                return 0;
            }

            // متابعة لاعب ⇒ مواعيد فريقه/منتخبه.
            foreach ($playerIds as $pid) {
                $teamIds = array_merge($teamIds, $this->client->teamIdsForPlayer($pid));
            }
            $teamIds = array_values(array_unique($teamIds));

            /** @var array<int,array<string,mixed>> $byGame */
            $byGame = [];
            $collect = function (array $fixtures) use (&$byGame): void {
                foreach ($fixtures as $f) {
                    $byGame[$f['game_id']] = $f; // dedup بمعرّف المباراة
                }
            };

            foreach ($compIds as $cid) {
                $collect($this->client->fixturesByCompetition($cid));
            }
            foreach ($teamIds as $tid) {
                $collect($this->client->fixturesByTeam($tid));
            }
            foreach ($matchIds as $mid) {
                $game = $this->client->gameById($mid);
                if ($game !== null) {
                    $byGame[$game['game_id']] = $game;
                }
            }

            $synced = 0;
            foreach ($byGame as $f) {
                if ($f['status'] === 'finished') {
                    continue; // المنتهية لا تُذكَّر ولا تُستطلَع
                }
                SportFixture::updateOrCreate(
                    ['game_id' => $f['game_id']],
                    [
                        'competition_id' => $f['competition_id'],
                        'season_num' => $f['season_num'],
                        'home_team_id' => $f['home_team_id'],
                        'home_name' => $f['home_name'],
                        'away_team_id' => $f['away_team_id'],
                        'away_name' => $f['away_name'],
                        'status' => $f['status'],
                        'start_at' => $f['start_at'],
                        // كادنس الاستطلاع: من الانطلاق (الكتلة C تقدّمه أثناء المباراة).
                        'next_poll_at' => $f['start_at'],
                    ],
                );
                $synced++;
            }

            // تقليم المواعيد المنقضية (قبل أمس) — إبقاء المرآة رشيقة.
            SportFixture::query()->whereNotNull('start_at')->where('start_at', '<', now()->subDay())->delete();

            return $synced;
        } finally {
            $lock->release();
        }
    }

    /** @return array<int,int> معرّفات الكيانات المتابَعة (DISTINCT) لنوعٍ ما */
    private function followedIds(FollowableType $type): array
    {
        return Follow::query()
            ->where('followable_type', $type->value)
            ->distinct()
            ->pluck('followable_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
