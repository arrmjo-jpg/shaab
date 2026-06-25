<?php

declare(strict_types=1);

namespace App\Actions\Admin\Ai;

use App\Http\Resources\Admin\Ai\AiUsageResource;
use App\Models\AiUsage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * رؤية تشغيلية لاستخدام الذكاء الاصطناعي — قابلة للاستعلام والترشيح (مَن/مزوّد/
 * عملية/مصدر/نطاق تاريخ). تُرجع: قائمة مُرقَّمة مُرشَّحة + إجماليات (اليوم/الشهر:
 * طلبات/توكِنات/تكلفة) + توزيع حسب المزوّد والعملية + اتجاه يومي (آخر 30 يوماً) +
 * الحدود المُهيّأة والمتبقّي منها. الإجماليات تُحسب على النداءات الفعلية (source=ai).
 *
 * التوكِنات والتكلفة تقديرية (عقد المزوّد يُعيد نصّاً فقط بلا عدّ توكِنات دقيق).
 */
class ShowAiUsageAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $rows = QueryBuilder::for(AiUsage::class)
            ->with('user:id,name')
            ->allowedFilters(
                AllowedFilter::exact('provider'),
                AllowedFilter::exact('action'),
                AllowedFilter::exact('source'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::callback('from', function ($q, $value): void {
                    $q->where('created_at', '>=', Carbon::parse($value)->startOfDay());
                }),
                AllowedFilter::callback('to', function ($q, $value): void {
                    $q->where('created_at', '<=', Carbon::parse($value)->endOfDay());
                }),
            )
            ->defaultSort('-id')
            ->allowedSorts('id', 'created_at', 'tokens', 'estimated_cost')
            ->paginate($perPage)
            ->appends(request()->query());

        return ApiResponse::success(
            data: AiUsageResource::collection($rows)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $rows->total(),
                    'count' => $rows->count(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'total_pages' => $rows->lastPage(),
                ],
                'totals' => [
                    'today' => $this->totals(now()->startOfDay()),
                    'month' => $this->totals(now()->startOfMonth()),
                ],
                'by_provider' => $this->groupSums('provider', now()->startOfMonth()),
                'by_action' => $this->groupSums('action', now()->startOfMonth()),
                'trend' => $this->dailyTrend(30),
                'caps' => $this->caps(),
            ]
        );
    }

    /** إجماليات النداءات الفعلية (source=ai) منذ لحظة. */
    private function totals(\DateTimeInterface $since): array
    {
        $row = AiUsage::query()
            ->where('source', 'ai')
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as requests, COALESCE(SUM(tokens),0) as tokens, COALESCE(SUM(estimated_cost),0) as cost')
            ->first();

        return [
            'requests' => (int) ($row->requests ?? 0),
            'tokens' => (int) ($row->tokens ?? 0),
            'estimated_cost' => round((float) ($row->cost ?? 0), 6),
        ];
    }

    /** توزيع الطلبات/التكلفة حسب عمود (provider|action) منذ لحظة. */
    private function groupSums(string $column, \DateTimeInterface $since): array
    {
        return AiUsage::query()
            ->where('source', 'ai')
            ->where('created_at', '>=', $since)
            ->groupBy($column)
            ->selectRaw("$column as label, COUNT(*) as requests, COALESCE(SUM(tokens),0) as tokens, COALESCE(SUM(estimated_cost),0) as cost")
            ->get()
            ->map(fn ($r): array => [
                'label' => (string) $r->label,
                'requests' => (int) $r->requests,
                'tokens' => (int) $r->tokens,
                'estimated_cost' => round((float) $r->cost, 6),
            ])
            ->all();
    }

    /** اتجاه يومي (طلبات/تكلفة) لآخر N يوماً — للرسم البياني الإداري. */
    private function dailyTrend(int $days): array
    {
        return AiUsage::query()
            ->where('source', 'ai')
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as requests, COALESCE(SUM(estimated_cost),0) as cost')
            ->get()
            ->map(fn ($r): array => [
                'day' => (string) $r->day,
                'requests' => (int) $r->requests,
                'estimated_cost' => round((float) $r->cost, 6),
            ])
            ->all();
    }

    /** الحدود المُهيّأة (config) + الاستهلاك الحالي + المتبقّي (0 = بلا حدّ). */
    private function caps(): array
    {
        $caps = (array) config('ai.caps', []);
        $today = $this->totals(now()->startOfDay());
        $month = $this->totals(now()->startOfMonth());

        $remaining = fn (int $cap, int $used): ?int => $cap > 0 ? max(0, $cap - $used) : null;
        $remainingCost = fn (float $cap, float $used): ?float => $cap > 0 ? round(max(0, $cap - $used), 6) : null;

        $daily = (int) ($caps['daily_requests'] ?? 0);
        $monthly = (int) ($caps['monthly_requests'] ?? 0);
        $userDaily = (int) ($caps['user_daily_requests'] ?? 0);
        $budget = (float) ($caps['monthly_budget_usd'] ?? 0);

        return [
            'daily_requests' => $daily,
            'monthly_requests' => $monthly,
            'user_daily_requests' => $userDaily,
            'monthly_budget_usd' => $budget,
            'remaining' => [
                'daily_requests' => $remaining($daily, $today['requests']),
                'monthly_requests' => $remaining($monthly, $month['requests']),
                'monthly_budget_usd' => $remainingCost($budget, $month['estimated_cost']),
            ],
        ];
    }
}
