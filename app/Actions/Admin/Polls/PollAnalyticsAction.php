<?php

declare(strict_types=1);

namespace App\Actions\Admin\Polls;

use App\Models\Poll;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات أسطول الاستطلاعات (عبر-الاستطلاعات، حساب-عند-القراءة) — مقاييس حقيقية فقط
 * مشتقّة من مصدر الحقيقة الموثوق (poll_votes / poll_vote_options)؛ عدّاد
 * poll_options.votes_count عدّاد أداء فقط ولا يُقرأ هنا. لا جدول تجميع (rollup) —
 * مرآة ReelAnalyticsAction (مجاميع، بلا نطاق زمني للأسطول) لكن مكيّفة للاستطلاع.
 *
 *  • مؤشّرات: إجمالي/نشِط/مفتوح + إجمالي المصوّتين + إجمالي الاختيارات (عبر استطلاعات حيّة).
 *  • تفصيل الحالة (مفتوح/مجدوَل/مغلق/مُعطَّل) — مشتقّ لحظياً عبر Poll::state().
 *  • المتصدّرون (الأكثر مشاركة) — ترتيب بعدد المصوّتين الفريدين (poll_votes).
 *  • مشاركة حديثة — أصوات/يوم لآخر 30 يوماً (مُعبّأة بأصفار) عبر الاستطلاعات الحيّة.
 */
class PollAnalyticsAction
{
    private const TOP_LIMIT = 10;

    private const TREND_DAYS = 30;

    public function handle(): JsonResponse
    {
        $data = Cache::remember(CacheKeys::pollAnalytics(), CacheTtl::SHORT, fn (): array => $this->compute());

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(): array
    {
        return [
            'kpis' => $this->kpis(),
            'status_breakdown' => $this->statusBreakdown(),
            'top_polls' => $this->topPolls(),
            'recent_participation' => $this->recentParticipation(),
        ];
    }

    /**
     * مؤشّرات الأسطول. الأصوات/الاختيارات محسوبة عبر استطلاعات حيّة (غير محذوفة) فقط
     * — تطابق مجموعة الاستطلاعات المعروضة. المصدر poll_votes/poll_vote_options (الحقيقة).
     *
     * @return array{total_polls:int,active_polls:int,open_polls:int,total_votes:int,total_selections:int}
     */
    private function kpis(): array
    {
        $totalVotes = (int) DB::table('poll_votes as pv')
            ->join('polls as p', 'p.id', '=', 'pv.poll_id')
            ->whereNull('p.deleted_at')
            ->count();

        $totalSelections = (int) DB::table('poll_vote_options as pvo')
            ->join('poll_votes as pv', 'pv.id', '=', 'pvo.poll_vote_id')
            ->join('polls as p', 'p.id', '=', 'pv.poll_id')
            ->whereNull('p.deleted_at')
            ->count();

        return [
            'total_polls' => Poll::query()->count(),
            'active_polls' => Poll::query()->active()->count(),
            'open_polls' => Poll::query()->votable()->count(),
            'total_votes' => $totalVotes,
            'total_selections' => $totalSelections,
        ];
    }

    /**
     * تفصيل الحالة المشتقّة لحظياً (تعتمد على now() — لا تُحسَب في SQL). عدد الاستطلاعات
     * في CMS منخفض؛ مسح أعمدة رفيعة لكل الاستطلاعات الحيّة مقبول.
     *
     * @return array{open:int,scheduled:int,closed:int,inactive:int}
     */
    private function statusBreakdown(): array
    {
        $counts = ['open' => 0, 'scheduled' => 0, 'closed' => 0, 'inactive' => 0];

        Poll::query()
            ->select(['id', 'is_active', 'starts_at', 'ends_at'])
            ->get()
            ->each(function (Poll $poll) use (&$counts): void {
                $counts[$poll->state()]++;
            });

        return $counts;
    }

    /**
     * الأكثر مشاركة — ترتيب بعدد المصوّتين الفريدين. كل صفّ poll_votes = مصوّت فريد
     * (فرادة (poll_id, voter_hash)) فيكون COUNT(pv.id) عدد المصوّتين الفريدين بدقّة.
     *
     * @return list<array<string,mixed>>
     */
    private function topPolls(): array
    {
        return Poll::query()
            ->leftJoin('poll_votes as pv', 'pv.poll_id', '=', 'polls.id')
            ->groupBy('polls.id', 'polls.uuid', 'polls.question', 'polls.is_active', 'polls.starts_at', 'polls.ends_at')
            ->select('polls.id', 'polls.uuid', 'polls.question', 'polls.is_active', 'polls.starts_at', 'polls.ends_at')
            ->selectRaw('COUNT(pv.id) as unique_voters')
            ->orderByDesc('unique_voters')
            ->orderByDesc('polls.id')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn (Poll $poll): array => [
                'id' => $poll->id,
                'uuid' => $poll->uuid,
                'question' => $poll->question,
                'state' => $poll->state(),
                'unique_voters' => (int) $poll->unique_voters,
            ])
            ->all();
    }

    /**
     * مشاركة الأسطول اليوميّة لآخر 30 يوماً (مُعبّأة بأصفار) — أصوات/يوم عبر الاستطلاعات
     * الحيّة. التجميع بـ DATE() متوافق مع MySQL/SQLite (نمط مُستخدَم في ShowAiUsageAction).
     *
     * @return array{days:int,points:list<array{date:string,votes:int}>,totals:array{votes:int}}
     */
    private function recentParticipation(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $from = $today->subDays(self::TREND_DAYS - 1);

        $rows = DB::table('poll_votes as pv')
            ->join('polls as p', 'p.id', '=', 'pv.poll_id')
            ->whereNull('p.deleted_at')
            ->where('pv.created_at', '>=', $from->toDateString())
            ->where('pv.created_at', '<', $today->addDay()->toDateString())
            ->groupByRaw('DATE(pv.created_at)')
            ->selectRaw('DATE(pv.created_at) as day, COUNT(*) as votes')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->day);

        $points = [];
        $total = 0;
        for ($day = $from; $day->lte($today); $day = $day->addDay()) {
            $key = $day->toDateString();
            $votes = (int) ($rows->get($key)->votes ?? 0);
            $points[] = ['date' => $key, 'votes' => $votes];
            $total += $votes;
        }

        return ['days' => self::TREND_DAYS, 'points' => $points, 'totals' => ['votes' => $total]];
    }
}
