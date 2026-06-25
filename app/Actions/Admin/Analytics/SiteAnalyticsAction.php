<?php

declare(strict_types=1);

namespace App\Actions\Admin\Analytics;

use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\Broadcast;
use App\Models\Epaper;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Reel;
use App\Models\Video;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * لوحة تحليلات الموقع (v1، موحّدة، قراءة-فقط) — تجميع رفيع عبر الأنظمة الفرعية القائمة
 * دون أي domain جديد. مُكاش (CacheKeys::siteAnalytics + CacheTtl::SHORT) لتفادي المجاميع
 * الثقيلة لكل تحميل.
 *
 *  • KPIs التفاعل: SUM(engagement_counters) على morphs المحتوى (مقال/ريل/فيديو).
 *  • جرد المحتوى: COUNT المنشور/الإجمالي لكل نوع.
 *  • الإعلانات: SUM(ad_stats_daily).  • الاستطلاعات: عدّ البطاقات.
 *  • الاتجاه: SUM(content_daily_stats.views) اليوميّ آخر 30 يوماً.
 *  • المتصدّرون: أعلى محتوى بالوزن — نفس صيغة ArticleAnalyticsAction/Reel/Video.
 *  • القنوات: تقسيم مصادر المرور الخمسة (content_daily_stats §A، نافذة 30 يوماً).
 */
class SiteAnalyticsAction
{
    private const TREND_DAYS = 30;

    private const TOP_LIMIT = 5;

    public function handle(): JsonResponse
    {
        $data = Cache::remember(CacheKeys::siteAnalytics(), CacheTtl::SHORT, fn (): array => $this->compute());

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(): array
    {
        $articleMorph = (new Article)->getMorphClass();
        $reelMorph = (new Reel)->getMorphClass();
        $videoMorph = (new Video)->getMorphClass();
        $contentMorphs = [$articleMorph, $reelMorph, $videoMorph];

        return [
            'engagement' => $this->engagement($contentMorphs),
            'inventory' => $this->inventory(),
            'ads' => $this->ads(),
            'polls' => ['votes' => PollVote::query()->count()],
            'trend' => $this->trend($contentMorphs),
            'top' => [
                'articles' => $this->topByScore(Article::query()->published(), 'articles', $articleMorph),
                'news' => $this->topByScore(
                    Article::query()->published()->where('articles.type', ArticleType::News->value),
                    'articles',
                    $articleMorph,
                ),
                'reels' => $this->topByScore(Reel::query()->published(), 'reels', $reelMorph),
                'videos' => $this->topByScore(Video::query()->published(), 'videos', $videoMorph),
            ],
            'channels' => $this->channels($contentMorphs),
        ];
    }

    /**
     * @param  list<string>  $morphs
     * @return array{views:int,likes:int,favorites:int}
     */
    private function engagement(array $morphs): array
    {
        $agg = DB::table('engagement_counters')
            ->whereIn('engageable_type', $morphs)
            ->selectRaw('COALESCE(SUM(views),0) v, COALESCE(SUM(likes),0) l, COALESCE(SUM(favorites),0) f')
            ->first();

        return [
            'views' => (int) ($agg->v ?? 0),
            'likes' => (int) ($agg->l ?? 0),
            'favorites' => (int) ($agg->f ?? 0),
        ];
    }

    /** @return array<string,int> */
    private function inventory(): array
    {
        return [
            'articles' => Article::query()->published()->count(),
            'reels' => Reel::query()->published()->count(),
            'videos' => Video::query()->published()->count(),
            'broadcasts' => Broadcast::query()->count(),
            'polls' => Poll::query()->count(),
            'epapers' => Epaper::query()->count(),
        ];
    }

    /** @return array{impressions:int,clicks:int} */
    private function ads(): array
    {
        $agg = DB::table('ad_stats_daily')
            ->selectRaw('COALESCE(SUM(impressions),0) imp, COALESCE(SUM(clicks),0) clk')
            ->first();

        return [
            'impressions' => (int) ($agg->imp ?? 0),
            'clicks' => (int) ($agg->clk ?? 0),
        ];
    }

    /**
     * اتجاه مشاهدات المحتوى آخر 30 يوماً (سلسلة متّصلة مملوءة بالأصفار).
     *
     * @param  list<string>  $morphs
     * @return list<array{date:string,views:int}>
     */
    private function trend(array $morphs): array
    {
        $today = now()->startOfDay();
        $from = $today->copy()->subDays(self::TREND_DAYS - 1);

        $rows = DB::table('content_daily_stats')
            ->whereIn('engageable_type', $morphs)
            ->where('day', '>=', $from->toDateString())
            ->selectRaw('day, COALESCE(SUM(views),0) v')
            ->groupBy('day')
            ->get()
            // استعلام خام (لا cast نموذج) — قد يعيد المحرّك 'Y-m-d 00:00:00'؛ نطبّع إلى
            // 'Y-m-d' ليطابق مفتاح الحلقة (toDateString).
            ->keyBy(fn ($r): string => substr((string) $r->day, 0, 10));

        $points = [];
        for ($d = $from->copy(); $d->lessThanOrEqualTo($today); $d->addDay()) {
            $key = $d->toDateString();
            $points[] = ['date' => $key, 'views' => (int) ($rows->get($key)->v ?? 0)];
        }

        return $points;
    }

    /**
     * أعلى المحتوى بالوزن (مشاهدة·1 + إعجاب·4 + مفضّلة·6 − عدم·2) — نفس صيغة
     * ArticleAnalyticsAction/Reel/Video على engagement_counters القائم (قراءة فقط).
     *
     * @param  Builder  $base  استعلام مُقيَّد سلفاً (published)
     * @return list<array{id:int,title:string,views:int,score:int}>
     */
    private function topByScore(Builder $base, string $table, string $morph): array
    {
        $score = '(COALESCE(ec.views,0) * 1 + COALESCE(ec.likes,0) * 4'
            .' + COALESCE(ec.favorites,0) * 6 - COALESCE(ec.dislikes,0) * 2)';

        return $base
            ->leftJoin('engagement_counters as ec', function (JoinClause $join) use ($morph, $table): void {
                $join->on('ec.engageable_id', '=', "{$table}.id")
                    ->where('ec.engageable_type', '=', $morph);
            })
            ->select("{$table}.id", "{$table}.title")
            ->selectRaw('COALESCE(ec.views,0) as views')
            ->selectRaw("{$score} as score")
            ->orderByDesc('score')
            ->orderByDesc("{$table}.published_at")
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($r): array => [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'views' => (int) $r->views,
                'score' => (int) $r->score,
            ])->all();
    }

    /**
     * تقسيم مصادر المرور الخمسة آخر 30 يوماً (content_daily_stats §A — القنوات
     * المجمَّعة فقط؛ لا نطاقات/URLs، تلك §B.4 مؤجَّلة).
     *
     * @param  list<string>  $morphs
     * @return array{direct:int,internal:int,search:int,social:int,referral:int}
     */
    private function channels(array $morphs): array
    {
        $from = now()->startOfDay()->subDays(self::TREND_DAYS - 1)->toDateString();

        $agg = DB::table('content_daily_stats')
            ->whereIn('engageable_type', $morphs)
            ->where('day', '>=', $from)
            ->selectRaw('COALESCE(SUM(views_direct),0) d, COALESCE(SUM(views_internal),0) i, '
                .'COALESCE(SUM(views_search),0) se, COALESCE(SUM(views_social),0) so, '
                .'COALESCE(SUM(views_referral),0) r')
            ->first();

        return [
            'direct' => (int) ($agg->d ?? 0),
            'internal' => (int) ($agg->i ?? 0),
            'search' => (int) ($agg->se ?? 0),
            'social' => (int) ($agg->so ?? 0),
            'referral' => (int) ($agg->r ?? 0),
        ];
    }
}
