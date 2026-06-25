<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoResource;
use App\Models\Video;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة فيديوهات الإدارة — ترشيح/فرز/ترقيم + رؤية المحذوف (trashed).
 */
class ListVideosAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = QueryBuilder::for(Video::class)
            ->with(['author:id,name', 'mediaAsset', 'category:id,name,slug', 'engagementCounter'])
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('visibility'),
                AllowedFilter::exact('locale'),
                AllowedFilter::exact('source_type'),
                AllowedFilter::exact('is_featured'),
                AllowedFilter::exact('video_category_id'),
                AllowedFilter::exact('author_id'),
                AllowedFilter::partial('title'),
            )
            ->allowedSorts('id', 'title', 'created_at', 'published_at', 'views_count', 'sort_order')
            ->defaultSort('-created_at');

        $trashed = (string) request()->query('trashed', '');
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        $videos = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: VideoResource::collection($videos)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $videos->total(),
                    'count' => $videos->count(),
                    'per_page' => $videos->perPage(),
                    'current_page' => $videos->currentPage(),
                    'total_pages' => $videos->lastPage(),
                ],
            ]
        );
    }
}
