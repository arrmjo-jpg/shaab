<?php

declare(strict_types=1);

namespace App\Actions\Admin\WriterRequests;

use App\Http\Resources\Admin\WriterRequests\WriterRequestResource;
use App\Models\WriterRequest;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListWriterRequestsAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $requests = QueryBuilder::for(WriterRequest::class)
            ->with(['user:id,name,email', 'reviewer:id,name'])
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->whereHas('user', function ($q) use ($value): void {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    });
                }),
            )
            ->defaultSort('-id')
            ->allowedSorts('id', 'created_at')
            ->paginate($perPage)
            ->appends(request()->query());

        return ApiResponse::success(
            data: WriterRequestResource::collection($requests)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $requests->total(),
                    'count' => $requests->count(),
                    'per_page' => $requests->perPage(),
                    'current_page' => $requests->currentPage(),
                    'total_pages' => $requests->lastPage(),
                ],
            ]
        );
    }
}
