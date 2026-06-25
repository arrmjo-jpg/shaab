<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\ArticleStatus;
use App\Enums\EngagementType;
use App\Models\Article;
use App\Support\Analytics\AnalyticsRange;
use App\Support\Analytics\DailyEngagementReader;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات مقال واحد (v1) — مقاييس حقيقية فقط من البنية القائمة (لا تيليمتري جديد):
 *
 *  • التفاعل: مجاميع تراكمية (engagement_counters) + المتفاعلون الفريدون (لقطة) + المعدّل.
 *  • السلاسل الزمنية: المشاهدات/التفاعل اليوميّ من content_daily_stats (إلى-الأمام فقط).
 *  • مصادر الزيارات: تفصيل القناة الخشن من نفس التجميع اليوميّ.
 *  • الأداء: نقاط الترند الموزونة + السرعة (مشاهدات/يوم) + الزخم (نصف حديث مقابل سابق)
 *    + مقارنة بخطّ الأساس (متوسّط المقالات المنشورة) — جاهزية المقارنة.
 *  • النشر: وقت النشر/التمييز/اللغة + ترجمات الـ translation_group (مقالات لغوية شقيقة).
 *
 * مرآة ReelEntityAnalyticsAction على نفس بنية التفاعل القائمة؛ دون كتلة الـ watch/discovery
 * المؤجّلة الخاصّة بالمقاطع (لا تنطبق على المقال) ومع إضافة نوع المقال (type).
 */
class ArticleEntityAnalyticsAction
{
    public function handle(Article $article, ?string $range, ?string $from = null, ?string $to = null): JsonResponse
    {
        $window = AnalyticsRange::resolve($range, $from, $to);

        $data = Cache::remember(
            "article:analytics:entity:{$article->id}:{$window->key()}:v1",
            CacheTtl::SHORT,
            fn (): array => $this->compute($article, $window),
        );

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(Article $article, AnalyticsRange $window): array
    {
        $article->loadMissing(['engagementCounter']);
        $morph = $article->getMorphClass();
        $metrics = $article->engagementMetrics();
        $series = DailyEngagementReader::read($morph, $article->id, $window);

        return [
            'entity' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'locale' => $article->locale,
                'type' => $article->type->value,
                'status' => $article->status->value,
                'is_featured' => (bool) $article->is_featured,
                'published_at' => $article->published_at?->toISOString(),
                'created_at' => $article->created_at?->toISOString(),
            ],
            'engagement' => [
                'views' => $metrics['views'],
                'likes' => $metrics['likes'],
                'dislikes' => $metrics['dislikes'],
                'favorites' => $metrics['favorites'], // saves
                'unique_reactors' => $this->uniqueReactors($morph, $article->id),
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
                'status' => $article->status->value,
                'is_featured' => (bool) $article->is_featured,
                'published_at' => $article->published_at?->toISOString(),
                'is_scheduled' => $article->published_at !== null && $article->published_at->isFuture(),
                'days_since_publish' => $article->published_at !== null && $article->published_at->isPast()
                    ? (int) $article->published_at->diffInDays(now())
                    : null,
                'locale' => $article->locale,
                'translations' => $this->translations($article),
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

    /** نفس وزن الترند العام للمقال: views·1 + likes·4 + favorites·6 − dislikes·2. */
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
     * خطّ الأساس للمقارنة: متوسّط مشاهدات المقال المنشور (إجمالي المشاهدات ÷ عدد المنشور،
     * فالمقالات بلا تفاعل تُحتسب صفراً بعدالة) + موضع هذا المقال مقابله.
     *
     * @return array{published_articles:int,avg_views:int,vs_baseline_pct:?float}
     */
    private function baseline(string $morph, int $views): array
    {
        $count = Article::query()->published()->count();

        $sum = (int) DB::table('engagement_counters as ec')
            ->join('articles as a', 'a.id', '=', 'ec.engageable_id')
            ->where('ec.engageable_type', $morph)
            ->whereNull('a.deleted_at')
            ->where('a.status', ArticleStatus::Published->value)
            ->where('a.published_at', '<=', now())
            ->sum('ec.views');

        $avg = $count > 0 ? (int) round($sum / $count) : 0;

        return [
            'published_articles' => $count,
            'avg_views' => $avg,
            'vs_baseline_pct' => $avg > 0 ? round((($views - $avg) / $avg) * 100, 1) : null,
        ];
    }

    /** @return list<array{id:int,locale:string,title:string,slug:string}> */
    private function translations(Article $article): array
    {
        if ($article->translation_group === null || $article->translation_group === '') {
            return [];
        }

        return Article::query()
            ->where('translation_group', $article->translation_group)
            ->where('id', '!=', $article->id)
            ->limit(10)
            ->get(['id', 'locale', 'title', 'slug'])
            ->map(fn (Article $r): array => [
                'id' => $r->id,
                'locale' => $r->locale,
                'title' => $r->title,
                'slug' => $r->slug,
            ])->all();
    }
}
