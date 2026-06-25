<?php

declare(strict_types=1);

namespace App\Actions\Admin\Activity;

use App\Http\Resources\Admin\Activity\AdminActivityResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListActivityLogAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $activities = QueryBuilder::for(Activity::class)
            ->with('causer:id,name')
            ->allowedFilters(
                // log_name مفهرس · event مفهرس (هجرة التشديد)
                AllowedFilter::exact('log_name'),
                AllowedFilter::exact('event'),
                // المنفِّذ: نضمّ causer_type ليُستخدَم الفهرس المركّب (causer_type, causer_id)
                AllowedFilter::callback('causer', function ($q, $value): void {
                    $q->where('causer_type', (new User)->getMorphClass())
                        ->where('causer_id', $value);
                }),
                // نطاق تاريخ sargable على عمود created_at المفهرس (لا whereDate)
                AllowedFilter::callback('from', function ($q, $value): void {
                    $q->where('created_at', '>=', Carbon::parse($value)->startOfDay());
                }),
                AllowedFilter::callback('to', function ($q, $value): void {
                    $q->where('created_at', '<=', Carbon::parse($value)->endOfDay());
                }),
            )
            ->defaultSort('-id')
            ->allowedSorts('id', 'created_at')
            ->paginate($perPage)
            ->appends(request()->query());

        return ApiResponse::success(
            data: AdminActivityResource::collection($activities)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $activities->total(),
                    'count' => $activities->count(),
                    'per_page' => $activities->perPage(),
                    'current_page' => $activities->currentPage(),
                    'total_pages' => $activities->lastPage(),
                ],
            ]
        );
    }
}
