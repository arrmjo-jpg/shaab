<?php

declare(strict_types=1);

namespace App\Actions\Admin\Polls;

use App\Models\Poll;
use App\Support\Analytics\AnalyticsRange;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات استطلاع مفرد (حساب-عند-القراءة) — مقاييس حقيقية فقط مشتقّة من مصدر الحقيقة
 * الموثوق (poll_votes / poll_vote_options)؛ عدّاد poll_options.votes_count لا يُقرأ هنا
 * (عدّاد أداء قد ينحرف). لا جدول تجميع — مرآة ReelEntityAnalyticsAction لكن مكيّفة:
 *
 *  • المشاركة: المصوّتون الفريدون (دقيق — فرادة (poll_id, voter_hash)) + إجمالي الاختيارات
 *    + متوسّط الاختيارات/مصوّت. فصل صريح بين «مصوّت فريد» و«اختيار».
 *  • التوزيع: أصوات كل خيار (حقيقة poll_vote_options) + نسبته من إجمالي الاختيارات.
 *  • الاتجاه: مشاركة يوميّة (أصوات/يوم من poll_votes.created_at) ضمن النطاق، مُعبّأة بأصفار.
 *    تاريخ كامل (لا تيليمتري مُبوَّب) — poll_votes موثوق منذ إنشاء الاستطلاع.
 *
 * الكاش: مفتاح يحمل النطاق + بصمة updated_at؛ TTL طويل للمغلق (بيانات ثابتة) وقصير للمفتوح.
 * تغيُّر updated_at (تعديل/إعادة تفعيل) يبدّل المفتاح فيُبطِل كاش المغلق دون وسوم/forget.
 */
class PollEntityAnalyticsAction
{
    public function handle(Poll $poll, ?string $range, ?string $from = null, ?string $to = null): JsonResponse
    {
        $window = AnalyticsRange::resolve($range, $from, $to);
        $signature = (string) ($poll->updated_at?->getTimestamp() ?? 0);

        $data = Cache::remember(
            CacheKeys::pollEntityAnalytics($poll->id, $window->key(), $signature),
            $poll->isClosed() ? CacheTtl::LONG : CacheTtl::SHORT,
            fn (): array => $this->compute($poll, $window),
        );

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(Poll $poll, AnalyticsRange $window): array
    {
        $poll->loadMissing('options');

        $uniqueVoters = (int) DB::table('poll_votes')->where('poll_id', $poll->id)->count();
        $optionCounts = $this->optionCounts($poll->id);
        $totalSelections = array_sum($optionCounts);

        return [
            'entity' => [
                'id' => $poll->id,
                'uuid' => $poll->uuid,
                'question' => $poll->question,
                'state' => $poll->state(),
                'is_active' => (bool) $poll->is_active,
                'allow_multiple' => (bool) $poll->allow_multiple,
                'audience_mode' => $poll->audience_mode->value,
                'result_visibility' => $poll->result_visibility->value,
                'starts_at' => $poll->starts_at?->toISOString(),
                'ends_at' => $poll->ends_at?->toISOString(),
                'created_at' => $poll->created_at?->toISOString(),
            ],
            'participation' => [
                // دقيق نسبةً لنموذج الهوية المقبول — يُعرَض طبيعياً (ليس تقريبياً).
                'unique_voters' => $uniqueVoters,
                'total_selections' => $totalSelections,
                'avg_selections_per_voter' => $uniqueVoters > 0
                    ? round($totalSelections / $uniqueVoters, 2)
                    : 0.0,
                'options_count' => $poll->options->count(),
            ],
            'distribution' => $poll->options
                ->sortBy('sort_order')
                ->map(fn ($option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'sort_order' => (int) $option->sort_order,
                    'votes' => (int) ($optionCounts[$option->id] ?? 0),
                    'percentage' => $totalSelections > 0
                        ? round((int) ($optionCounts[$option->id] ?? 0) / $totalSelections * 100, 2)
                        : 0.0,
                ])
                ->values()
                ->all(),
            'trend' => $this->participationTrend($poll->id, $window),
        ];
    }

    /**
     * أصوات كل خيار من مصدر الحقيقة (poll_vote_options ⋈ poll_votes لهذا الاستطلاع) —
     * مستقلّ عن عدّاد votes_count. مفتاح المصفوفة = poll_option_id.
     *
     * @return array<int,int>
     */
    private function optionCounts(int $pollId): array
    {
        $counts = [];

        DB::table('poll_vote_options as pvo')
            ->join('poll_votes as pv', 'pv.id', '=', 'pvo.poll_vote_id')
            ->where('pv.poll_id', $pollId)
            ->groupBy('pvo.poll_option_id')
            ->selectRaw('pvo.poll_option_id as option_id, COUNT(*) as votes')
            ->get()
            ->each(function ($row) use (&$counts): void {
                $counts[(int) $row->option_id] = (int) $row->votes;
            });

        return $counts;
    }

    /**
     * مشاركة يوميّة (أصوات فريدة/يوم) ضمن النطاق، مُعبّأة بأصفار. نطاق نصف-مفتوح
     * [from, to+1) على created_at + DATE() للتجميع (متوافق MySQL/SQLite).
     *
     * @return array{window:array{range:string,from:string,to:string,days:int},points:list<array{date:string,votes:int}>,totals:array{votes:int}}
     */
    private function participationTrend(int $pollId, AnalyticsRange $window): array
    {
        $rows = DB::table('poll_votes')
            ->where('poll_id', $pollId)
            ->where('created_at', '>=', $window->from->toDateString())
            ->where('created_at', '<', $window->to->addDay()->toDateString())
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as votes')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->day);

        $points = [];
        $total = 0;
        for ($day = $window->from; $day->lte($window->to); $day = $day->addDay()) {
            $key = $day->toDateString();
            $votes = (int) ($rows->get($key)->votes ?? 0);
            $points[] = ['date' => $key, 'votes' => $votes];
            $total += $votes;
        }

        return [
            'window' => $window->toArray(),
            'points' => $points,
            'totals' => ['votes' => $total],
        ];
    }
}
