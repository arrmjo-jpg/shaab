<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Actions\ApproveCampaignAction;
use App\Modules\Notifications\Actions\CancelCampaignAction;
use App\Modules\Notifications\Actions\PauseCampaignAction;
use App\Modules\Notifications\Actions\ResumeCampaignAction;
use App\Modules\Notifications\Actions\StoreCampaignAction;
use App\Modules\Notifications\Http\Requests\StoreCampaignRequest;
use App\Modules\Notifications\Http\Resources\CampaignResource;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Models\NotificationCampaignChannel;
use App\Modules\Notifications\Support\CampaignTransitionException;
use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * إدارة حملات الإشعار — تحكّم رفيع (Actions + Resources). التأليف اليدويّ يمرّ عبر
 * NotificationManager (المدخل الوحيد). دورة الحياة (approve/pause/resume/cancel) عبر actions
 * ذرّيّة؛ تعارض الحالة ⇒ 409. التفويض عبر permission middleware على المسارات.
 */
final class CampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $campaigns = QueryBuilder::for(NotificationCampaign::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('event_key'),
                AllowedFilter::exact('source'),
                AllowedFilter::exact('priority'),
            ])
            ->allowedSorts(['created_at', 'scheduled_at', 'finished_at', 'status'])
            ->defaultSort('-created_at')
            ->withCount('channels')
            ->withSum('channels', 'targeted')
            ->withSum('channels', 'sent')
            ->withSum('channels', 'failed')
            ->withSum('channels', 'skipped')
            ->withSum('channels', 'invalid')
            ->paginate($perPage)
            ->appends(request()->query());

        return ApiResponse::success(
            data: CampaignResource::collection($campaigns)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $campaigns->total(),
                    'count' => $campaigns->count(),
                    'per_page' => $campaigns->perPage(),
                    'current_page' => $campaigns->currentPage(),
                    'total_pages' => $campaigns->lastPage(),
                ],
            ],
        );
    }

    public function show(NotificationCampaign $campaign): JsonResponse
    {
        return $this->present($campaign, withChannels: true);
    }

    public function store(StoreCampaignRequest $request, StoreCampaignAction $action): JsonResponse
    {
        try {
            $campaign = $action->handle($request->validated(), $request->user()?->id);
        } catch (CampaignTransitionException $e) {
            return ApiResponse::error(message: $e->getMessage(), status: 422);
        }

        return $this->present($campaign, withChannels: true, status: 201);
    }

    public function approve(NotificationCampaign $campaign, ApproveCampaignAction $action): JsonResponse
    {
        return $this->transition($campaign, fn (): NotificationCampaign => $action->handle($campaign));
    }

    public function pause(NotificationCampaign $campaign, PauseCampaignAction $action): JsonResponse
    {
        return $this->transition($campaign, fn (): NotificationCampaign => $action->handle($campaign));
    }

    public function resume(NotificationCampaign $campaign, ResumeCampaignAction $action): JsonResponse
    {
        return $this->transition($campaign, fn (): NotificationCampaign => $action->handle($campaign));
    }

    public function cancel(NotificationCampaign $campaign, CancelCampaignAction $action): JsonResponse
    {
        return $this->transition($campaign, fn (): NotificationCampaign => $action->handle($campaign));
    }

    /** ملخّص لوحة — توزيع الحالات + إجماليّات الإرسال. */
    public function summary(): JsonResponse
    {
        $byStatus = NotificationCampaign::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totals = NotificationCampaignChannel::query()
            ->selectRaw('COALESCE(SUM(sent),0) sent, COALESCE(SUM(failed),0) failed, COALESCE(SUM(skipped),0) skipped, COALESCE(SUM(invalid),0) invalid')
            ->first();

        return ApiResponse::success(data: [
            'by_status' => $byStatus,
            'totals' => [
                'sent' => (int) ($totals->sent ?? 0),
                'failed' => (int) ($totals->failed ?? 0),
                'skipped' => (int) ($totals->skipped ?? 0),
                'invalid' => (int) ($totals->invalid ?? 0),
            ],
        ]);
    }

    private function transition(NotificationCampaign $campaign, Closure $run): JsonResponse
    {
        try {
            $updated = $run();
        } catch (CampaignTransitionException $e) {
            return ApiResponse::error(message: $e->getMessage(), status: 409);
        }

        return $this->present($updated, withChannels: true);
    }

    private function present(NotificationCampaign $campaign, bool $withChannels = false, int $status = 200): JsonResponse
    {
        $query = NotificationCampaign::query()
            ->withCount('channels')
            ->withSum('channels', 'targeted')
            ->withSum('channels', 'sent')
            ->withSum('channels', 'failed')
            ->withSum('channels', 'skipped')
            ->withSum('channels', 'invalid')
            ->whereKey($campaign->getKey());

        if ($withChannels) {
            $query->with(['channels' => fn ($q) => $q->orderBy('channel_priority')]);
        }

        $fresh = $query->firstOrFail();

        return ApiResponse::success(data: (new CampaignResource($fresh))->resolve(), status: $status);
    }
}
