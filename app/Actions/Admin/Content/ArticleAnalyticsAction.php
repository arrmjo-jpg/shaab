<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات أسطول المقالات (v1، عبر-المقالات) — مجاميع حقيقية فقط. مرآة
 * ReelAnalyticsAction على بيانات التفاعل القائمة (engagement_counters) دون أي بنية
 * جديدة. القيم تراكمية (مدى كامل) — تُوسَم زمنياً في الواجهة؛ تُكمّل صفحة المقال
 * المفردة (التي تحمل النطاق الزمني + الاتجاهات).
 *
 *  • المتصدّرون (لوحة الصدارة بالوزن) — جاهزية المقارنة.
 *  • أداء وقت النشر (متوسّط المشاهدات حسب ساعة النشر).
 *  • تقسيم اللغة (مشاهدات/عدد حسب اللغة).
 *  • أثر التمييز (متوسّط مشاهدات المميَّز مقابل العادي + الرفع %).
 */
class ArticleAnalyticsAction
{
    private const TOP_LIMIT = 10;

    private const PUBLISH_SCAN_CAP = 5000;

    public function handle(): JsonResponse
    {
        $data = Cache::remember('article:analytics:fleet:v1', CacheTtl::SHORT, fn (): array => $this->compute());

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(): array
    {
        $morph = (new Article)->getMorphClass();

        return [
            'engagement' => $this->engagementTotals($morph),
            'top_performers' => $this->topPerformers($morph),
            'publish_time' => $this->publishTime($morph),
            'language' => $this->language($morph),
            'featured_impact' => $this->featuredImpact($morph),
        ];
    }

    /** @return array{views:int,likes:int,dislikes:int,favorites:int} */
    private function engagementTotals(string $morph): array
    {
        $agg = DB::table('engagement_counters as ec')
            ->join('articles as a', 'a.id', '=', 'ec.engageable_id')
            ->where('ec.engageable_type', $morph)
            ->whereNull('a.deleted_at')
            ->selectRaw('COALESCE(SUM(ec.views),0) as views, COALESCE(SUM(ec.likes),0) as likes, '
                .'COALESCE(SUM(ec.dislikes),0) as dislikes, COALESCE(SUM(ec.favorites),0) as favorites')
            ->first();

        return [
            'views' => (int) ($agg->views ?? 0),
            'likes' => (int) ($agg->likes ?? 0),
            'dislikes' => (int) ($agg->dislikes ?? 0),
            'favorites' => (int) ($agg->favorites ?? 0),
        ];
    }

    /** @return list<array<string,mixed>> أعلى المقالات بالوزن (مشاهدة·1 + إعجاب·4 + مفضّلة·6 − عدم·2). */
    private function topPerformers(string $morph): array
    {
        $score = '(COALESCE(ec.views,0) * 1 + COALESCE(ec.likes,0) * 4'
            .' + COALESCE(ec.favorites,0) * 6 - COALESCE(ec.dislikes,0) * 2)';

        return Article::query()
            ->published()
            ->leftJoin('engagement_counters as ec', function (JoinClause $join) use ($morph): void {
                $join->on('ec.engageable_id', '=', 'articles.id')->where('ec.engageable_type', '=', $morph);
            })
            ->select('articles.id', 'articles.title', 'articles.slug', 'articles.locale', 'articles.is_featured')
            ->selectRaw('COALESCE(ec.views,0) as views')
            ->selectRaw("{$score} as score")
            ->orderByDesc('score')
            ->orderByDesc('articles.published_at')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($r): array => [
                'id' => $r->id,
                'title' => $r->title,
                'slug' => $r->slug,
                'locale' => $r->locale,
                'is_featured' => (bool) $r->is_featured,
                'views' => (int) $r->views,
                'score' => (int) $r->score,
            ])->all();
    }

    /**
     * متوسّط المشاهدات حسب ساعة النشر (0–23) — تجميع في PHP (محدود) لتفادي تبعية دوال
     * التاريخ بين MySQL/SQLite. الساعة بتوقيت التخزين.
     *
     * @return list<array{hour:int,articles:int,avg_views:int}>
     */
    private function publishTime(string $morph): array
    {
        $rows = Article::query()
            ->published()
            ->leftJoin('engagement_counters as ec', function (JoinClause $join) use ($morph): void {
                $join->on('ec.engageable_id', '=', 'articles.id')->where('ec.engageable_type', '=', $morph);
            })
            ->orderByDesc('articles.published_at')
            ->limit(self::PUBLISH_SCAN_CAP)
            ->get(['articles.published_at as published_at', DB::raw('COALESCE(ec.views,0) as views')]);

        $buckets = array_fill(0, 24, ['articles' => 0, 'views' => 0]);
        foreach ($rows as $row) {
            if ($row->published_at === null) {
                continue;
            }
            $hour = (int) Carbon::parse($row->published_at)->hour;
            $buckets[$hour]['articles']++;
            $buckets[$hour]['views'] += (int) $row->views;
        }

        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $b = $buckets[$h];
            $out[] = [
                'hour' => $h,
                'articles' => $b['articles'],
                'avg_views' => $b['articles'] > 0 ? (int) round($b['views'] / $b['articles']) : 0,
            ];
        }

        return $out;
    }

    /** @return list<array{locale:string,articles:int,views:int}> */
    private function language(string $morph): array
    {
        return Article::query()
            ->published()
            ->leftJoin('engagement_counters as ec', function (JoinClause $join) use ($morph): void {
                $join->on('ec.engageable_id', '=', 'articles.id')->where('ec.engageable_type', '=', $morph);
            })
            ->groupBy('articles.locale')
            ->selectRaw('articles.locale as locale, COUNT(DISTINCT articles.id) as articles, COALESCE(SUM(ec.views),0) as views')
            ->get()
            ->map(fn ($r): array => [
                'locale' => (string) $r->locale,
                'articles' => (int) $r->articles,
                'views' => (int) $r->views,
            ])->all();
    }

    /** @return array{featured:array{articles:int,avg_views:int},regular:array{articles:int,avg_views:int},lift_pct:?float} */
    private function featuredImpact(string $morph): array
    {
        $rows = Article::query()
            ->published()
            ->leftJoin('engagement_counters as ec', function (JoinClause $join) use ($morph): void {
                $join->on('ec.engageable_id', '=', 'articles.id')->where('ec.engageable_type', '=', $morph);
            })
            ->groupBy('articles.is_featured')
            ->selectRaw('articles.is_featured as is_featured, COUNT(DISTINCT articles.id) as articles, AVG(COALESCE(ec.views,0)) as avg_views')
            ->get();

        $featured = ['articles' => 0, 'avg_views' => 0];
        $regular = ['articles' => 0, 'avg_views' => 0];
        foreach ($rows as $row) {
            $bucket = ['articles' => (int) $row->articles, 'avg_views' => (int) round((float) $row->avg_views)];
            if ((bool) $row->is_featured) {
                $featured = $bucket;
            } else {
                $regular = $bucket;
            }
        }

        $lift = $regular['avg_views'] > 0
            ? round((($featured['avg_views'] - $regular['avg_views']) / $regular['avg_views']) * 100, 1)
            : null;

        return ['featured' => $featured, 'regular' => $regular, 'lift_pct' => $lift];
    }
}
