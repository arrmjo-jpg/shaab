<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\LiveUpdateResource;
use App\Models\Article;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قائمة تحديثات التغطية الحيّة (لوحة الإدارة) — مرقّمة، بترتيب الخط الزمني.
 * لا كاش: واجهة تحرير متغيّرة بكثافة (نمط مطابق لقائمة المقالات الإدارية).
 */
class ListLiveUpdatesAction
{
    public function handle(Article $article): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $updates = $article->liveUpdates()
            ->timelineOrder()
            ->with(['author:id,name', 'mediaAssets'])
            ->paginate($perPage)
            ->appends(request()->query());

        return ApiResponse::success(
            data: LiveUpdateResource::collection($updates)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $updates->total(),
                    'count' => $updates->count(),
                    'per_page' => $updates->perPage(),
                    'current_page' => $updates->currentPage(),
                    'total_pages' => $updates->lastPage(),
                ],
            ]
        );
    }
}
