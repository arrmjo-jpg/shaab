<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\EngagementType;
use App\Enums\ReelStatus;
use App\Models\Reel;
use App\Support\Analytics\AnalyticsRange;
use App\Support\Analytics\DailyEngagementReader;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات ريل واحد (v1) — مقاييس حقيقية فقط من البنية القائمة (لا تيليمتري مشاهدة/ظهور):
 *
 *  • التفاعل: مجاميع تراكمية (engagement_counters) + المتفاعلون الفريدون (لقطة) + المعدّل.
 *  • السلاسل الزمنية: المشاهدات/التفاعل اليوميّ من content_daily_stats (إلى-الأمام فقط).
 *  • مصادر الزيارات: تفصيل القناة الخشن من نفس التجميع اليوميّ.
 *  • الأداء: نقاط الترند الموزونة + السرعة (مشاهدات/يوم) + الزخم (نصف حديث مقابل سابق)
 *    + مقارنة بخطّ الأساس (متوسّط الريلز المنشورة) — جاهزية المقارنة.
 *  • النشر: وقت النشر/التمييز/اللغة + ترجمات الـ translation_group (مقاطع لغوية شقيقة).
 *
 * مقاييس قصيرة-الشكل (إكمال/متوسّط مشاهدة/تسرّب/منحنى/تكرارات/تمرير + ظهور/اكتشاف
 * الخلاصة/إسناد التوصية): مؤجّلة بصدق (available=false) — تتطلّب تيليمتري ابتلاع مستقلّ.
 */
class ReelEntityAnalyticsAction
{
    public function handle(Reel $reel, ?string $range, ?string $from = null, ?string $to = null): JsonResponse
    {
        $window = AnalyticsRange::resolve($range, $from, $to);

        $data = Cache::remember(
            "reel:analytics:entity:{$reel->id}:{$window->key()}:v1",
            CacheTtl::SHORT,
            fn (): array => $this->compute($reel, $window),
        );

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(Reel $reel, AnalyticsRange $window): array
    {
        $reel->loadMissing(['engagementCounter']);
        $morph = $reel->getMorphClass();
        $metrics = $reel->engagementMetrics();
        $series = DailyEngagementReader::read($morph, $reel->id, $window);

        return [
            'entity' => [
                'id' => $reel->id,
                'title' => $reel->title,
                'slug' => $reel->slug,
                'locale' => $reel->locale,
                'status' => $reel->status->value,
                'is_featured' => (bool) $reel->is_featured,
                'duration_seconds' => (int) $reel->duration_seconds,
                'published_at' => $reel->published_at?->toISOString(),
                'created_at' => $reel->created_at?->toISOString(),
            ],
            'engagement' => [
                'views' => $metrics['views'],
                'likes' => $metrics['likes'],
                'dislikes' => $metrics['dislikes'],
                'favorites' => $metrics['favorites'], // saves
                'unique_reactors' => $this->uniqueReactors($morph, $reel->id),
                'engagement_rate' => $this->engagementRate($metrics),
            ],
            'trend' => [
                'window' => $window->toArray(),
                'forward_only' => true,
                'points' => $series['points'],
                'totals' => $series['totals'],
            ],
            'traffic' => [
                'forward_only' => true,
                'total' => array_sum($series['channels']),
                'channels' => $series['channels'],
            ],
            'performance' => [
                'trending_score' => $this->trendingScore($metrics),
                'velocity_per_day' => $this->velocity($series['totals']['views'], $window->days()),
                'momentum_pct' => $this->momentum($series['points']),
                'baseline' => $this->baseline($morph, $metrics['views']),
            ],
            'publishing' => [
                'status' => $reel->status->value,
                'is_featured' => (bool) $reel->is_featured,
                'published_at' => $reel->published_at?->toISOString(),
                'is_scheduled' => $reel->published_at !== null && $reel->published_at->isFuture(),
                'days_since_publish' => $reel->published_at !== null && $reel->published_at->isPast()
                    ? (int) $reel->published_at->diffInDays(now())
                    : null,
                'locale' => $reel->locale,
                'translations' => $this->translations($reel),
            ],
            // مؤجّل بصدق — لا تيليمتري مشاهدة/ظهور قصير-الشكل بعد (مرحلة ابتلاع مستقلّة).
            'deferred' => [
                'watch' => [
                    'available' => false,
                    'reason' => 'telemetry_required',
                    'metrics' => ['completion_rate', 'avg_watch_percent', 'drop_off', 'retention_curve', 'loops', 'swipe_away', 'first_3s_retention'],
                ],
                'discovery' => [
                    'available' => false,
                    'reason' => 'telemetry_required',
                    'metrics' => ['impressions', 'feed_discovery', 'recommendation_attribution'],
                ],
            ],
        ];
    }

    private function uniqueReactors(string $morph, int $id): int
    {
        return (int) DB::table('engagements')
            ->where('engageable_type', $morph)
            ->where('engageable_id', $id)
            ->whereIn('type', [
                EngagementType::Like->value,
                EngagementType::Dislike->value,
                EngagementType::Favorite->value,
            ])
            ->distinct()
            ->count('actor_key');
    }

    /** @param array{views:int,likes:int,dislikes:int,favorites:int} $m */
    private function engagementRate(array $m): float
    {
        if ($m['views'] <= 0) {
            return 0.0;
        }

        return round((($m['likes'] + $m['dislikes'] + $m['favorites']) / $m['views']) * 100, 2);
    }

    /** نفس وزن الترند العام للريل: views·1 + likes·4 + favorites·6 − dislikes·2. */
    private function trendingScore(array $m): int
    {
        return (int) ($m['views'] * 1 + $m['likes'] * 4 + $m['favorites'] * 6 - $m['dislikes'] * 2);
    }

    private function velocity(int $views, int $days): float
    {
        return $days > 0 ? round($views / $days, 1) : (float) $views;
    }

    /**
     * الزخم: مجموع مشاهدات النصف الأحدث من النطاق مقابل النصف الأسبق (%). يحتاج ≥ يومين.
     *
     * @param  list<array{date:string,views:int,likes:int,dislikes:int,favorites:int}>  $points
     */
    private function momentum(array $points): ?float
    {
        $n = count($points);
        if ($n < 2) {
            return null;
        }

        $mid = intdiv($n, 2);
        $prior = array_sum(array_map(static fn (array $p): int => $p['views'], array_slice($points, 0, $mid)));
        $recent = array_sum(array_map(static fn (array $p): int => $p['views'], array_slice($points, $mid)));

        if ($prior <= 0) {
            return $recent > 0 ? 100.0 : 0.0;
        }

        return round((($recent - $prior) / $prior) * 100, 1);
    }

    /**
     * خطّ الأساس للمقارنة: متوسّط مشاهدات الريل المنشور (إجمالي المشاهدات ÷ عدد المنشور،
     * فالريلز بلا تفاعل تُحتسب صفراً بعدالة) + موضع هذا الريل مقابله.
     *
     * @param  array{views:int,likes:int,dislikes:int,favorites:int}  ...
     * @return array{published_reels:int,avg_views:int,vs_baseline_pct:?float}
     */
    private function baseline(string $morph, int $views): array
    {
        $count = Reel::query()->published()->count();

        $sum = (int) DB::table('engagement_counters as ec')
            ->join('reels as r', 'r.id', '=', 'ec.engageable_id')
            ->where('ec.engageable_type', $morph)
            ->whereNull('r.deleted_at')
            ->where('r.status', ReelStatus::Published->value)
            ->where('r.published_at', '<=', now())
            ->sum('ec.views');

        $avg = $count > 0 ? (int) round($sum / $count) : 0;

        return [
            'published_reels' => $count,
            'avg_views' => $avg,
            'vs_baseline_pct' => $avg > 0 ? round((($views - $avg) / $avg) * 100, 1) : null,
        ];
    }

    /** @return list<array{id:int,locale:string,title:string,slug:string}> */
    private function translations(Reel $reel): array
    {
        if ($reel->translation_group === null || $reel->translation_group === '') {
            return [];
        }

        return Reel::query()
            ->where('translation_group', $reel->translation_group)
            ->where('id', '!=', $reel->id)
            ->limit(10)
            ->get(['id', 'locale', 'title', 'slug'])
            ->map(fn (Reel $r): array => [
                'id' => $r->id,
                'locale' => $r->locale,
                'title' => $r->title,
                'slug' => $r->slug,
            ])->all();
    }
}
