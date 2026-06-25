<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdZone;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات الإعلانات (تجميعيّة) — انطباعات/نقرات/CTR + اتجاه يوميّ + أعلى الحملات/الإبداعات/
 * المساحات + تقسيم القنوات، من ad_stats_daily (التجميع اليوميّ المُزال-التطبيع) ضمن نطاق
 * زمنيّ. كاش قصير المدى (مجاميع ثقيلة لا تُعاد لكل تحميل). الأبعاد المشتقّة تبقى للتاريخ
 * بعد حذف الكيان فتُحلّ الأسماء عبر withTrashed.
 */
class AdAnalyticsAction
{
    private const TOP_LIMIT = 10;

    private const MAX_DAYS = 366;

    public function handle(Request $request): JsonResponse
    {
        [$from, $to, $range] = $this->window($request);
        $key = 'ads:analytics:'.$range.':'.$from->toDateString().':'.$to->toDateString();

        $data = Cache::remember($key, CacheTtl::SHORT, fn (): array => $this->compute($from, $to, $range));

        return ApiResponse::success(data: $data);
    }

    /** @return array{0:Carbon,1:Carbon,2:string} */
    private function window(Request $request): array
    {
        $range = (string) ($request->query('range') ?: '7d');
        $today = now()->startOfDay();

        return match ($range) {
            '24h' => [$today->copy(), $today->copy(), '24h'],
            '30d' => [$today->copy()->subDays(29), $today->copy(), '30d'],
            'custom' => $this->customWindow($request, $today),
            default => [$today->copy()->subDays(6), $today->copy(), '7d'],
        };
    }

    /** @return array{0:Carbon,1:Carbon,2:string} */
    private function customWindow(Request $request, Carbon $today): array
    {
        $from = $this->parseDate((string) $request->query('from'));
        $to = $this->parseDate((string) $request->query('to'));

        if ($from === null || $to === null) {
            return [$today->copy()->subDays(6), $today->copy(), '7d'];
        }
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
            $from = $to->copy()->subDays(self::MAX_DAYS - 1);
        }

        return [$from, $to, 'custom'];
    }

    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private function compute(Carbon $from, Carbon $to, string $range): array
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $base = fn () => DB::table('ad_stats_daily')->whereBetween('day', [$fromStr, $toStr]);

        $agg = $base()->selectRaw(
            'COALESCE(SUM(impressions),0) imp, COALESCE(SUM(clicks),0) clk, '
            .'COALESCE(SUM(impressions_direct),0) c_direct, COALESCE(SUM(impressions_internal),0) c_internal, '
            .'COALESCE(SUM(impressions_search),0) c_search, COALESCE(SUM(impressions_social),0) c_social, '
            .'COALESCE(SUM(impressions_referral),0) c_referral'
        )->first();

        $imp = (int) ($agg->imp ?? 0);
        $clk = (int) ($agg->clk ?? 0);

        return [
            'window' => ['range' => $range, 'from' => $fromStr, 'to' => $toStr, 'days' => $from->diffInDays($to) + 1],
            'totals' => ['impressions' => $imp, 'clicks' => $clk, 'ctr' => $this->ctr($imp, $clk)],
            'trend' => ['points' => $this->trend($base, $from, $to)],
            'channels' => [
                'direct' => (int) ($agg->c_direct ?? 0),
                'internal' => (int) ($agg->c_internal ?? 0),
                'search' => (int) ($agg->c_search ?? 0),
                'social' => (int) ($agg->c_social ?? 0),
                'referral' => (int) ($agg->c_referral ?? 0),
            ],
            'top_campaigns' => $this->top($base, 'ad_campaign_id', AdCampaign::class, 'name'),
            'top_creatives' => $this->top($base, 'ad_creative_id', AdCreative::class, 'title'),
            'top_zones' => $this->top($base, 'ad_zone_id', AdZone::class, 'name'),
        ];
    }

    private function ctr(int $imp, int $clk): float
    {
        return $imp > 0 ? round($clk / $imp * 100, 2) : 0.0;
    }

    /**
     * اتجاه يوميّ مملوء بالأصفار — نقطة لكل يوم في النطاق (سلسلة متّصلة للرسم).
     *
     * @param  Closure():Builder  $base
     * @return array<int,array<string,mixed>>
     */
    private function trend(Closure $base, Carbon $from, Carbon $to): array
    {
        $rows = $base()
            ->selectRaw('day, COALESCE(SUM(impressions),0) imp, COALESCE(SUM(clicks),0) clk')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($r): string => (string) $r->day);

        $points = [];
        for ($d = $from->copy(); $d->lessThanOrEqualTo($to); $d->addDay()) {
            $key = $d->toDateString();
            $row = $rows->get($key);
            $points[] = [
                'date' => $key,
                'impressions' => (int) ($row->imp ?? 0),
                'clicks' => (int) ($row->clk ?? 0),
            ];
        }

        return $points;
    }

    /**
     * أعلى البُعد (حملة/إبداع/مساحة) بالانطباعات، مع تحليل الأسماء (withTrashed للتاريخ).
     *
     * @param  Closure():Builder  $base
     * @param  class-string<Model>  $model
     * @return array<int,array<string,mixed>>
     */
    private function top(Closure $base, string $column, string $model, string $nameField): array
    {
        $rows = $base()
            ->whereNotNull($column)
            ->selectRaw("{$column} as dim, COALESCE(SUM(impressions),0) imp, COALESCE(SUM(clicks),0) clk")
            ->groupBy($column)
            ->orderByDesc('imp')
            ->limit(self::TOP_LIMIT)
            ->get();

        $ids = $rows->pluck('dim')->all();
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($model), true);

        $names = $model::query()
            ->when($usesSoftDeletes, fn ($q) => $q->withTrashed())
            ->whereIn('id', $ids)
            ->pluck($nameField, 'id');

        return $rows->map(fn ($r): array => [
            'id' => (int) $r->dim,
            'name' => (string) ($names[$r->dim] ?? ('#'.$r->dim)),
            'impressions' => (int) $r->imp,
            'clicks' => (int) $r->clk,
            'ctr' => $this->ctr((int) $r->imp, (int) $r->clk),
        ])->all();
    }
}
