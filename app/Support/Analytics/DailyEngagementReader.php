<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use App\Models\ContentDailyStat;

/**
 * قارئ التجميع اليوميّ للتفاعل (content_daily_stats) — يبني سلسلة زمنية متّصلة
 * (تعبئة الأيام الغائبة بأصفار) + مجاميع النطاق + تفصيل قنوات الزيارة. مشترك بين
 * تحليلات الفيديو والبثّ (كلاهما engageable). تيليمتري إلى-الأمام فقط (منذ بدء التتبّع).
 */
final class DailyEngagementReader
{
    /**
     * @return array{
     *   points: list<array{date:string,views:int,likes:int,dislikes:int,favorites:int}>,
     *   totals: array{views:int,likes:int,dislikes:int,favorites:int},
     *   channels: array{direct:int,internal:int,search:int,social:int,referral:int}
     * }
     */
    public static function read(string $morph, int $id, AnalyticsRange $window): array
    {
        // نصف-مفتوح [from, to+1) بسلاسل تاريخ — متين سواء خُزّن day كتاريخ صرف أو بطابع
        // زمنيّ (00:00:00) عبر cast النموذج، ويبقى صديقاً للفهرس.
        $rows = ContentDailyStat::query()
            ->where('engageable_type', $morph)
            ->where('engageable_id', $id)
            ->where('day', '>=', $window->from->toDateString())
            ->where('day', '<', $window->to->addDay()->toDateString())
            ->orderBy('day')
            ->get()
            ->keyBy(fn (ContentDailyStat $r): string => $r->day->toDateString());

        $points = [];
        $totals = ['views' => 0, 'likes' => 0, 'dislikes' => 0, 'favorites' => 0];
        $channels = ['direct' => 0, 'internal' => 0, 'search' => 0, 'social' => 0, 'referral' => 0];

        for ($day = $window->from; $day->lte($window->to); $day = $day->addDay()) {
            $key = $day->toDateString();
            $row = $rows->get($key);

            $views = (int) ($row->views ?? 0);
            $likes = (int) ($row->likes ?? 0);
            $dislikes = (int) ($row->dislikes ?? 0);
            $favorites = (int) ($row->favorites ?? 0);

            $points[] = compact('views', 'likes', 'dislikes', 'favorites') + ['date' => $key];

            $totals['views'] += $views;
            $totals['likes'] += $likes;
            $totals['dislikes'] += $dislikes;
            $totals['favorites'] += $favorites;

            if ($row !== null) {
                $channels['direct'] += (int) $row->views_direct;
                $channels['internal'] += (int) $row->views_internal;
                $channels['search'] += (int) $row->views_search;
                $channels['social'] += (int) $row->views_social;
                $channels['referral'] += (int) $row->views_referral;
            }
        }

        return ['points' => $points, 'totals' => $totals, 'channels' => $channels];
    }
}
