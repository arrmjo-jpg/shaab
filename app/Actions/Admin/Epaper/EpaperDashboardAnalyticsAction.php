<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Models\Epaper;
use App\Models\EpaperArchiveSearchDaily;
use App\Models\EpaperDailyStat;
use App\Models\EpaperPageView;
use App\Models\EpaperSearchTerm;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * لوحة تحليلات القارئ المؤسّسيّة (Final completion) — تقرير عابر للأعداد مبنيّ على
 * التجميعات اليوميّة (epaper_daily_stats) فيدعم مرشّحات المدى الزمنيّ، إضافةً إلى
 * سلوك القارئ من العدّادات القائمة. بيانات حقيقيّة مجمَّعة فقط (لا PII، لا تلفيق).
 *
 * ملاحظات صدق: أكثر الصفحات/العبارات «كل-الوقت» (لا بُعد زمنيّ على عدّاديهما)؛
 * «الرائج» نافذة ثابتة (7 أيّام مقابل 7 سابقة) مستقلّة عن مرشّح اللوحة.
 */
class EpaperDashboardAnalyticsAction
{
    private const TOP_ISSUES = 20;

    private const TOP_PAGES = 15;

    private const TOP_TERMS = 20;

    private const TRENDING = 8;

    public function handle(string $period, ?string $from, ?string $to): JsonResponse
    {
        [$from, $to] = $this->resolveRange($period, $from, $to);

        return ApiResponse::success(__('epaper.analytics.shown'), [
            'range' => ['period' => $period, 'from' => $from, 'to' => $to],
            'overview' => $this->overview($from, $to),
            'series' => $this->series($from, $to),
            'top_issues' => $this->topIssues($from, $to),
            'trending' => $this->trending(),
            'reader_behavior' => $this->readerBehavior(),
        ]);
    }

    /** @return array{0:string,1:string} [from, to] بصيغة Y-m-d (توقيت التطبيق). */
    private function resolveRange(string $period, ?string $from, ?string $to): array
    {
        $today = now()->toDateString();

        return match ($period) {
            'today' => [$today, $today],
            '7d' => [now()->subDays(6)->toDateString(), $today],
            'custom' => [$from ?: now()->subDays(29)->toDateString(), $to ?: $today],
            default => [now()->subDays(29)->toDateString(), $today], // 30d
        };
    }

    /** @return array<string,int> */
    private function overview(string $from, string $to): array
    {
        $agg = EpaperDailyStat::query()
            ->whereBetween('stat_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(opens),0) opens, COALESCE(SUM(sessions),0) sessions, COALESCE(SUM(total_duration_seconds),0) dur, COALESCE(SUM(pages_viewed),0) pv, COALESCE(SUM(searches),0) searches, COALESCE(SUM(bookmarks_used),0) bm, COALESCE(SUM(resumes_used),0) rs, COALESCE(SUM(downloads),0) dl')
            ->first();

        $sessions = (int) ($agg->sessions ?? 0);
        $duration = (int) ($agg->dur ?? 0);

        return [
            'opens' => (int) ($agg->opens ?? 0),
            'sessions' => $sessions,
            'total_duration_seconds' => $duration,
            'avg_session_seconds' => $sessions > 0 ? (int) round($duration / $sessions) : 0,
            'pages_viewed' => (int) ($agg->pv ?? 0),
            'searches' => (int) ($agg->searches ?? 0),
            'bookmarks_used' => (int) ($agg->bm ?? 0),
            'resumes_used' => (int) ($agg->rs ?? 0),
            'downloads' => (int) ($agg->dl ?? 0),
            'archive_searches' => (int) EpaperArchiveSearchDaily::query()->whereBetween('stat_date', [$from, $to])->sum('count'),
            'active_issues' => EpaperDailyStat::query()->whereBetween('stat_date', [$from, $to])->distinct()->count('epaper_id'),
        ];
    }

    /** @return array<int,array<string,mixed>> سلسلة يوميّة لمخطّط الاتجاه. */
    private function series(string $from, string $to): array
    {
        $rows = EpaperDailyStat::query()
            ->whereBetween('stat_date', [$from, $to])
            ->selectRaw('stat_date, SUM(opens) opens, SUM(sessions) sessions, SUM(total_duration_seconds) dur, SUM(searches) searches, SUM(downloads) dl')
            ->groupBy('stat_date')->orderBy('stat_date')->get();

        $archive = EpaperArchiveSearchDaily::query()
            ->whereBetween('stat_date', [$from, $to])
            ->selectRaw('stat_date, SUM(count) c')->groupBy('stat_date')->pluck('c', 'stat_date');

        return $rows->map(fn ($r): array => [
            'date' => (string) $r->stat_date,
            'opens' => (int) $r->opens,
            'sessions' => (int) $r->sessions,
            'total_duration_seconds' => (int) $r->dur,
            'searches' => (int) $r->searches,
            'downloads' => (int) $r->dl,
            'archive_searches' => (int) ($archive[$r->stat_date] ?? 0),
        ])->all();
    }

    /** @return array<int,array<string,mixed>> أعلى الأعداد أداءً بدرجة التفاعل. */
    private function topIssues(string $from, string $to): array
    {
        $rows = EpaperDailyStat::query()
            ->whereBetween('stat_date', [$from, $to])
            ->selectRaw('epaper_id, SUM(opens) opens, SUM(sessions) sessions, SUM(total_duration_seconds) dur, SUM(pages_viewed) pv, SUM(searches) searches, SUM(bookmarks_used) bm, SUM(resumes_used) rs, SUM(downloads) dl')
            ->groupBy('epaper_id')->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $meta = Epaper::query()->whereIn('id', $rows->pluck('epaper_id'))->get(['id', 'title', 'issue_number'])->keyBy('id');

        return $rows->map(function ($r) use ($meta): array {
            $issue = $meta->get($r->epaper_id);
            $sessions = (int) $r->sessions;
            $duration = (int) $r->dur;

            return [
                'id' => (int) $r->epaper_id,
                'title' => $issue?->title ?? '—',
                'issue_number' => (int) ($issue?->issue_number ?? 0),
                'opens' => (int) $r->opens,
                'sessions' => $sessions,
                'total_duration_seconds' => $duration,
                'avg_session_seconds' => $sessions > 0 ? (int) round($duration / $sessions) : 0,
                'pages_viewed' => (int) $r->pv,
                'searches' => (int) $r->searches,
                'bookmarks_used' => (int) $r->bm,
                'resumes_used' => (int) $r->rs,
                'downloads' => (int) $r->dl,
                'engagement_score' => $this->score($sessions, $duration, (int) $r->searches, (int) $r->bm, (int) $r->rs, (int) $r->dl),
            ];
        })->sortByDesc('engagement_score')->take(self::TOP_ISSUES)->values()->all();
    }

    /**
     * درجة تفاعل تجريبيّة شفّافة فوق بيانات حقيقيّة: تكافئ العمق (المدّة/البحث/الإشارات/
     * الاستئناف/التنزيل) لا مجرّد الفتح. ليست مقياساً مطلقاً — أداة ترتيب نسبيّ.
     */
    private function score(int $sessions, int $duration, int $searches, int $bookmarks, int $resumes, int $downloads): int
    {
        return $sessions + (int) round($duration / 60) + $searches * 2 + $bookmarks * 3 + $resumes * 2 + $downloads * 4;
    }

    /** @return array<int,array<string,mixed>> الرائج: نافذة 7 أيّام مقابل 7 سابقة (جلسات). */
    private function trending(): array
    {
        $today = now()->toDateString();
        $recentFrom = now()->subDays(6)->toDateString();
        $priorFrom = now()->subDays(13)->toDateString();
        $priorTo = now()->subDays(7)->toDateString();

        $recent = EpaperDailyStat::query()->whereBetween('stat_date', [$recentFrom, $today])
            ->selectRaw('epaper_id, SUM(sessions) s')->groupBy('epaper_id')->pluck('s', 'epaper_id');

        if ($recent->isEmpty()) {
            return [];
        }

        $prior = EpaperDailyStat::query()->whereBetween('stat_date', [$priorFrom, $priorTo])
            ->selectRaw('epaper_id, SUM(sessions) s')->groupBy('epaper_id')->pluck('s', 'epaper_id');

        $meta = Epaper::query()->whereIn('id', $recent->keys())->get(['id', 'title', 'issue_number'])->keyBy('id');

        return $recent->map(function ($sessions, $id) use ($prior, $meta): array {
            $issue = $meta->get($id);
            $recentS = (int) $sessions;
            $priorS = (int) ($prior[$id] ?? 0);

            return [
                'id' => (int) $id,
                'title' => $issue?->title ?? '—',
                'issue_number' => (int) ($issue?->issue_number ?? 0),
                'recent_sessions' => $recentS,
                'prior_sessions' => $priorS,
                'growth' => $recentS - $priorS,
            ];
        })->sortByDesc('growth')->take(self::TRENDING)->values()->all();
    }

    /** @return array{top_pages:array<int,array<string,int>>,top_terms:array<int,array<string,mixed>>} */
    private function readerBehavior(): array
    {
        $pages = EpaperPageView::query()
            ->selectRaw('page_number, SUM(views) views')
            ->groupBy('page_number')->orderByDesc('views')->orderBy('page_number')
            ->limit(self::TOP_PAGES)->get();

        $terms = EpaperSearchTerm::query()
            ->selectRaw('term, SUM(count) count')
            ->groupBy('term')->orderByDesc('count')->orderBy('term')
            ->limit(self::TOP_TERMS)->get();

        return [
            'top_pages' => $pages->map(fn ($p): array => ['page' => (int) $p->page_number, 'views' => (int) $p->views])->all(),
            'top_terms' => $terms->map(fn ($t): array => ['term' => (string) $t->term, 'count' => (int) $t->count])->all(),
        ];
    }
}
