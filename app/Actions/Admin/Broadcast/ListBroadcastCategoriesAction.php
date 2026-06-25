<?php

declare(strict_types=1);

namespace App\Actions\Admin\Broadcast;

use App\Http\Resources\Admin\Broadcast\BroadcastCategoryResource;
use App\Models\BroadcastCategory;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قائمة تصنيفات البثّ — مسطّحة (FLAT، لا تشجير)، مرتّبة بـ sort_order ثم الاسم،
 * مع عدّاد البثّ لكل تصنيف.
 */
class ListBroadcastCategoriesAction
{
    public function handle(): JsonResponse
    {
        $categories = BroadcastCategory::query()
            ->withCount('broadcasts')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            data: BroadcastCategoryResource::collection($categories)->resolve()
        );
    }
}
