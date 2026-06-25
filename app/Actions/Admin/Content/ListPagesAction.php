<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\PageResource;
use App\Models\Page;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListPagesAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = QueryBuilder::for(Page::class)
            ->with('author:id,name')
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('locale'),
                AllowedFilter::exact('show_in_header'),
                AllowedFilter::exact('show_in_footer'),
                AllowedFilter::partial('title'),
            )
            ->allowedSorts('id', 'title', 'created_at', 'published_at', 'sort_order')
            ->defaultSort('sort_order', '-created_at');

        $trashed = (string) request()->query('trashed', '');
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        $pages = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: PageResource::collection($pages)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $pages->total(),
                    'count' => $pages->count(),
                    'per_page' => $pages->perPage(),
                    'current_page' => $pages->currentPage(),
                    'total_pages' => $pages->lastPage(),
                ],
            ]
        );
    }
}
