<?php

declare(strict_types=1);

namespace App\Support\Sport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * عميل 365 الخادميّ (قراءة عامّة بلا توكن) — مصدر مواعيد المباريات لمرآة `sport_fixtures` (نظام إشعارات «تابع»).
 * نمط المشروع: Http facade مضبوط المهلة، try/catch، يعيد بيانات منظَّمة بلا استثناءات (مهمّة المزامنة تتابع عند الفشل).
 * كلّ مباراة تُطبَّع إلى مصفوفة موحَّدة: game_id/competition_id/season_num/الفريقان(id+name)/start_at/status.
 */
final class Sport365Client
{
    /** مواعيد بطولة (كلّ مبارياتها القادمة) — `web/games/fixtures/?competitions={id}`. */
    public function fixturesByCompetition(int $competitionId): array
    {
        return $this->fixturesFrom($this->get('games/fixtures/', ['competitions' => $competitionId]));
    }

    /** مواعيد فريق — `web/games/fixtures/?competitors={teamId}`. */
    public function fixturesByTeam(int $teamId): array
    {
        return $this->fixturesFrom($this->get('games/fixtures/', ['competitors' => $teamId]));
    }

    /** مباراة مفردة (لمتابعة مباراة بعينها) — `web/game/?gameId={id}`. null إن لم تُحلَّ. */
    public function gameById(int $gameId): ?array
    {
        $json = $this->get('game/', ['gameId' => $gameId]);
        $game = is_array($json['game'] ?? null) ? $json['game'] : null;

        return $game ? $this->normalizeGame($game) : null;
    }

    /**
     * لقطة مباراة للاستطلاع الحيّ (الكتلة C) — `web/game/?gameId={id}`: الحالة المُطبَّعة + أحداثها. كلّ حدث يحمل
     * **مفتاح منع تكرار مُركَّبًا ثابتًا** (مستقلّ عن order/num ⇒ إعادة الترتيب لا تُغيّره):
     *   event:{gameId}-{eventTypeId}-{subTypeId}-{gameTime}-{addedTime}-{playerId}-{competitorId}  (كلّ null→0).
     * اسم اللاعب من `members`. null إن لم تُحلَّ المباراة.
     *
     * @return array{status:string, events:array<int,array<string,mixed>>}|null
     */
    public function gameSnapshot(int $gameId): ?array
    {
        $json = $this->get('game/', ['gameId' => $gameId]);
        $game = is_array($json['game'] ?? null) ? $json['game'] : null;
        if ($game === null) {
            return null;
        }

        $members = [];
        foreach ((is_array($game['members'] ?? null) ? $game['members'] : []) as $m) {
            if (is_array($m) && isset($m['id'])) {
                $members[(int) $m['id']] = (string) ($m['shortName'] ?? $m['name'] ?? '');
            }
        }

        $events = [];
        foreach ((is_array($game['events'] ?? null) ? $game['events'] : []) as $e) {
            if (! is_array($e)) {
                continue;
            }
            $type = is_array($e['eventType'] ?? null) ? $e['eventType'] : [];
            $typeId = (int) ($type['id'] ?? 0);
            $subTypeId = (int) ($type['subTypeId'] ?? 0);
            $gameTime = (int) ($e['gameTime'] ?? 0);
            $addedTime = (int) ($e['addedTime'] ?? 0);
            $playerId = (int) ($e['playerId'] ?? 0);
            $teamId = (int) ($e['competitorId'] ?? 0);

            $events[] = [
                // المفتاح المُركَّب (null مُطبَّعة إلى 0 عبر الـ cast أعلاه) — أساس منع التكرار.
                'dedup_key' => sprintf('event:%d-%d-%d-%d-%d-%d-%d', $gameId, $typeId, $subTypeId, $gameTime, $addedTime, $playerId, $teamId),
                'event_type_id' => $typeId,
                'label' => (string) ($type['subTypeName'] ?? $type['name'] ?? ''),
                'minute' => (string) ($e['gameTimeDisplay'] ?? ($gameTime > 0 ? "{$gameTime}'" : '')),
                'player_id' => $playerId,
                'player_name' => $members[$playerId] ?? null,
                'team_id' => $teamId,
            ];
        }

        return [
            'status' => $this->normalizeStatus((int) ($game['statusGroup'] ?? 0)),
            'events' => $events,
        ];
    }

    /**
     * فِرَق اللاعب (نادٍ/منتخب) — لتحويل متابعة لاعب إلى مواعيد فريقه — `web/athletes/?athletes={id}`.
     *
     * @return array<int,int> معرّفات الفِرَق
     */
    public function teamIdsForPlayer(int $athleteId): array
    {
        $json = $this->get('athletes/', ['athletes' => $athleteId]);
        $competitors = is_array($json['competitors'] ?? null) ? $json['competitors'] : [];

        return array_values(array_filter(array_map(
            static fn ($c): ?int => is_array($c) && isset($c['id']) ? (int) $c['id'] : null,
            $competitors,
        )));
    }

    /** نداء GET عامّ لـ365 (مع المعاملات المشتركة) — يعيد مصفوفة JSON أو [] عند أيّ فشل. */
    private function get(string $path, array $params): array
    {
        $base = rtrim((string) config('sport.api_base'), '/');
        $common = $this->commonParams();

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('sport.http_timeout', 8))
                ->get("{$base}/{$path}", $common + $params);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /** @return array<string,int|string> */
    private function commonParams(): array
    {
        parse_str((string) config('sport.api_common'), $parsed);

        return $parsed;
    }

    /** يطبّع مصفوفة `games` إلى مواعيد موحَّدة (يتجاهل ما لا معرّف له). */
    private function fixturesFrom(array $json): array
    {
        $games = is_array($json['games'] ?? null) ? $json['games'] : [];
        $out = [];
        foreach ($games as $g) {
            if (! is_array($g)) {
                continue;
            }
            $fixture = $this->normalizeGame($g);
            if ($fixture !== null) {
                $out[] = $fixture;
            }
        }

        return $out;
    }

    /** يطبّع مباراة 365 واحدة. null إن لا معرّف. */
    private function normalizeGame(array $g): ?array
    {
        $gameId = isset($g['id']) ? (int) $g['id'] : 0;
        if ($gameId <= 0) {
            return null;
        }
        $home = is_array($g['homeCompetitor'] ?? null) ? $g['homeCompetitor'] : [];
        $away = is_array($g['awayCompetitor'] ?? null) ? $g['awayCompetitor'] : [];

        return [
            'game_id' => $gameId,
            'competition_id' => isset($g['competitionId']) ? (int) $g['competitionId'] : 0,
            'season_num' => isset($g['seasonNum']) ? (int) $g['seasonNum'] : null,
            'home_team_id' => isset($home['id']) ? (int) $home['id'] : null,
            'home_name' => isset($home['name']) ? (string) $home['name'] : null,
            'away_team_id' => isset($away['id']) ? (int) $away['id'] : null,
            'away_name' => isset($away['name']) ? (string) $away['name'] : null,
            'start_at' => $this->parseTime($g['startTime'] ?? null),
            'status' => $this->normalizeStatus(isset($g['statusGroup']) ? (int) $g['statusGroup'] : 0),
        ];
    }

    private function parseTime(mixed $iso): ?Carbon
    {
        if (! is_string($iso) || $iso === '') {
            return null;
        }
        try {
            return Carbon::parse($iso);
        } catch (Throwable) {
            return null;
        }
    }

    /** statusGroup 365 → حالة مُطبَّعة. 3=جارية، ≥4=منتهية، غير ذلك=مجدولة. */
    private function normalizeStatus(int $statusGroup): string
    {
        return match (true) {
            $statusGroup === 3 => 'live',
            $statusGroup >= 4 => 'finished',
            default => 'scheduled',
        };
    }
}
