<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Http\Resources\Admin\Epaper\EpaperResource;
use App\Models\Epaper;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة الأعداد للإدارة — ترشيح (الحالة/اللغة) + بحث بالعنوان + فرز + ترقيم + رؤية
 * المحذوف. مرآة ListBroadcastsAction (نفس عقد الترقيم في meta).
 */
class ListEpapersAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = QueryBuilder::for(Epaper::class)
            ->with(['mediaAsset', 'author:id,name'])
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('locale'),
                AllowedFilter::exact('issue_number'),
                AllowedFilter::partial('title'),
            )
            ->allowedSorts('id', 'issue_number', 'publication_date', 'published_at', 'created_at')
            ->defaultSort('-publication_date');

        $trashed = (string) request()->query('trashed', '');
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        $epapers = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            message: __('epaper.listed'),
            data: EpaperResource::collection($epapers)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $epapers->total(),
                    'count' => $epapers->count(),
                    'per_page' => $epapers->perPage(),
                    'current_page' => $epapers->currentPage(),
                    'total_pages' => $epapers->lastPage(),
                ],
            ],
        );
    }
}
