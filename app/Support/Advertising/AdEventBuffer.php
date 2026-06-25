<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use App\Enums\AdEventType;
use App\Models\AdCounter;
use App\Models\AdPlacement;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * مخزن مؤقّت لأحداث الإعلان (انطباع/نقرة) — يزيل تنازع الصفّ الساخن تحت الحِمل. يجمّع
 * الزيادات في الكاش (Redis) ذرّياً لكل (نوع، إسناد، قناة)، ثم تُفرَّغ دورياً
 * (ads:flush-events) في زيادة واحدة لكل إسناد. مرآة ViewBuffer.
 *
 * عند التفريغ: تُجمَّع الانطباعات/النقرات لكل إسناد (مع تفصيل قناة الانطباع)، تُحلّ
 * الأبعاد المشتقّة دفعةً واحدة (placement → إبداع → حملة)، ثم تُكتب في العدّاد الحيّ
 * (AdCounter) والتجميع اليوميّ (AdStatsRollup). فقدان طفيف عند انهيار الكاش مقبول.
 */
final class AdEventBuffer
{
    private const DELTA_PREFIX = 'adbuf:delta:';

    private const DIRTY_INDEX = 'adbuf:dirty:index';

    private const DIRTY_LOCK = 'adbuf:dirty:lock';

    public static function supported(): bool
    {
        return Cache::getStore() instanceof LockProvider;
    }

    public static function add(string $type, int $placementId, string $channel = 'direct'): void
    {
        $n = (int) Cache::increment(self::deltaKey($type, $placementId, $channel));

        if ($n === 1) {
            Cache::lock(self::DIRTY_LOCK, 5)->block(3, function () use ($type, $placementId, $channel): void {
                $index = Cache::get(self::DIRTY_INDEX, []);
                $index[self::member($type, $placementId, $channel)] = true;
                Cache::forever(self::DIRTY_INDEX, $index);
            });
        }
    }

    /** يفرّغ كل الدلتا إلى العدّادات + التجميع اليوميّ. يُعيد عدد الإسنادات المُفرَّغة. */
    public static function flush(): int
    {
        if (! self::supported()) {
            return 0;
        }

        $members = [];
        Cache::lock(self::DIRTY_LOCK, 10)->block(5, function () use (&$members): void {
            $members = array_keys(Cache::get(self::DIRTY_INDEX, []));
            Cache::forget(self::DIRTY_INDEX);
        });

        /** @var array<int,array{impressions:int,clicks:int,channels:array<string,int>}> $byPlacement */
        $byPlacement = [];
        foreach ($members as $member) {
            [$type, $placementId, $channel] = self::parse($member);
            $delta = (int) Cache::pull(self::deltaKey($type, $placementId, $channel), 0);
            if ($delta <= 0) {
                continue;
            }
            $byPlacement[$placementId] ??= ['impressions' => 0, 'clicks' => 0, 'channels' => []];
            if ($type === AdEventType::Click->value) {
                $byPlacement[$placementId]['clicks'] += $delta;
            } else {
                $byPlacement[$placementId]['impressions'] += $delta;
                $byPlacement[$placementId]['channels'][$channel] = ($byPlacement[$placementId]['channels'][$channel] ?? 0) + $delta;
            }
        }

        if ($byPlacement === []) {
            return 0;
        }

        $derived = self::resolveDerived(array_keys($byPlacement));

        $flushed = 0;
        foreach ($byPlacement as $placementId => $agg) {
            self::applyCounter($placementId, $agg['impressions'], $agg['clicks']);
            $d = $derived[$placementId] ?? ['zone' => null, 'creative' => null, 'campaign' => null];
            AdStatsRollup::add(
                $placementId,
                $d['zone'],
                $d['creative'],
                $d['campaign'],
                $agg['impressions'],
                $agg['clicks'],
                $agg['channels'],
            );
            $flushed++;
        }

        return $flushed;
    }

    /**
     * يحلّ الأبعاد المشتقّة دفعةً (placement → مساحة/إبداع/حملة). null عند حذف الإسناد.
     *
     * @param  array<int,int>  $placementIds
     * @return array<int,array{zone:?int,creative:?int,campaign:?int}>
     */
    private static function resolveDerived(array $placementIds): array
    {
        $rows = AdPlacement::query()
            ->with('creative:id,ad_campaign_id')
            ->whereIn('id', $placementIds)
            ->get(['id', 'ad_creative_id', 'ad_zone_id']);

        $out = [];
        foreach ($rows as $p) {
            $out[(int) $p->id] = [
                'zone' => (int) $p->ad_zone_id,
                'creative' => (int) $p->ad_creative_id,
                'campaign' => $p->creative?->ad_campaign_id !== null ? (int) $p->creative->ad_campaign_id : null,
            ];
        }

        return $out;
    }

    private static function applyCounter(int $placementId, int $impressions, int $clicks): void
    {
        AdCounter::query()->firstOrCreate(['ad_placement_id' => $placementId]);

        $set = [];
        if ($impressions > 0) {
            $set['impressions'] = DB::raw('impressions + '.$impressions);
        }
        if ($clicks > 0) {
            $set['clicks'] = DB::raw('clicks + '.$clicks);
        }
        if ($set !== []) {
            AdCounter::query()->where('ad_placement_id', $placementId)->update($set);
        }
    }

    private static function deltaKey(string $type, int $id, string $channel): string
    {
        return self::DELTA_PREFIX.$type.':'.$id.':'.$channel;
    }

    private static function member(string $type, int $id, string $channel): string
    {
        return $type.'|'.$id.'|'.$channel;
    }

    /** @return array{0:string,1:int,2:string} */
    private static function parse(string $member): array
    {
        $parts = explode('|', $member, 3);

        return [$parts[0], (int) ($parts[1] ?? 0), $parts[2] ?? 'direct'];
    }
}
