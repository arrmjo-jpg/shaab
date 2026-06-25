<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Http\Resources\Admin\Profile\ProfileActivityResource;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListProfileActivityAction
{
    public function handle(User $user): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $morph = $user->getMorphClass();
        $id = $user->getKey();

        $activities = QueryBuilder::for(Activity::class)
            // النشاط الخاص بهذا المستخدم: ما فعله (causer) أو ما وقع عليه (subject).
            ->where(function ($q) use ($morph, $id): void {
                $q->where(fn ($x) => $x->where('causer_type', $morph)->where('causer_id', $id))
                    ->orWhere(fn ($x) => $x->where('subject_type', $morph)->where('subject_id', $id));
            })
            ->allowedFilters(
                AllowedFilter::exact('log_name'),
                AllowedFilter::exact('event'),
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
            data: ProfileActivityResource::collection($activities)->resolve(),
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
