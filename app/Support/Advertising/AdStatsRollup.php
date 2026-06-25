<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use App\Enums\TrafficChannel;
use Illuminate\Support\Facades\DB;

/**
 * كاتب التجميع اليوميّ لأداء الإعلان (ad_stats_daily) — محوريّة الإسناد + أبعاد مشتقّة
 * مُزالة-التطبيع (مساحة/إبداع/حملة). مرآة DailyEngagementRollup: ضمان صفّ اليوم
 * (insertOrIgnore عبر فرادة (إسناد،يوم)) ثم «col = col + delta» — ذرّيّ عبر MySQL/SQLite.
 */
final class AdStatsRollup
{
    private const TABLE = 'ad_stats_daily';

    /** أعمدة قابلة للزيادة (قائمة بيضاء — دفاع ضدّ الحقن). */
    private const COLUMNS = [
        'impressions', 'clicks',
        'impressions_direct', 'impressions_internal', 'impressions_search',
        'impressions_social', 'impressions_referral',
    ];

    /**
     * يضيف أداء اليوم لإسناد. الأبعاد المشتقّة تُكتب مرّة عند إنشاء صفّ اليوم.
     *
     * @param  array<string,int>  $impChannelDeltas  مفاتيحها قيم TrafficChannel
     */
    public static function add(
        int $placementId,
        ?int $zoneId,
        ?int $creativeId,
        ?int $campaignId,
        int $impressions,
        int $clicks,
        array $impChannelDeltas = [],
    ): void {
        $inc = [];
        if ($impressions > 0) {
            $inc['impressions'] = $impressions;
        }
        if ($clicks > 0) {
            $inc['clicks'] = $clicks;
        }

        foreach ($impChannelDeltas as $channel => $delta) {
            $delta = (int) $delta;
            if ($delta <= 0) {
                continue;
            }
            $tc = TrafficChannel::tryFrom((string) $channel) ?? TrafficChannel::Direct;
            $col = 'impressions_'.$tc->value;
            $inc[$col] = ($inc[$col] ?? 0) + $delta;
        }

        if ($inc === []) {
            return;
        }

        self::bump($placementId, $zoneId, $creativeId, $campaignId, $inc);
    }

    /** @param  array<string,int>  $increments */
    private static function bump(int $placementId, ?int $zoneId, ?int $creativeId, ?int $campaignId, array $increments): void
    {
        $now = now();
        $keys = ['ad_placement_id' => $placementId, 'day' => $now->toDateString()];

        DB::table(self::TABLE)->insertOrIgnore(array_merge($keys, [
            'ad_zone_id' => $zoneId,
            'ad_creative_id' => $creativeId,
            'ad_campaign_id' => $campaignId,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $set = ['updated_at' => $now];
        foreach ($increments as $col => $delta) {
            if (! in_array($col, self::COLUMNS, true)) {
                continue;
            }
            $set[$col] = DB::raw($col.' + '.(int) $delta);
        }

        if (count($set) > 1) {
            DB::table(self::TABLE)->where($keys)->update($set);
        }
    }
}
