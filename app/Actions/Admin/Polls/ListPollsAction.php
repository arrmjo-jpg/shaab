<?php

declare(strict_types=1);

namespace App\Actions\Admin\Polls;

use App\Http\Resources\Admin\Polls\PollResource;
use App\Models\Poll;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة استطلاعات الإدارة — ترشيح/فرز/ترقيم + رؤية المحذوف (trashed=only|with). تشمل
 * عدّاد الخيارات. تتبع اتفاقية الترقيم الموحّدة (performance.pagination + meta.pagination).
 */
class ListPollsAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = QueryBuilder::for(Poll::class)
            ->withCount('options')
            ->allowedFilters(
                AllowedFilter::exact('is_active'),
                AllowedFilter::partial('question'),
            )
            ->allowedSorts('id', 'question', 'starts_at', 'ends_at', 'created_at')
            ->defaultSort('-created_at');

        $trashed = (string) request()->query('trashed', '');
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        $polls = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: PollResource::collection($polls)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $polls->total(),
                    'count' => $polls->count(),
                    'per_page' => $polls->perPage(),
                    'current_page' => $polls->currentPage(),
                    'total_pages' => $polls->lastPage(),
                ],
            ]
        );
    }
}
