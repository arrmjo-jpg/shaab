<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\CategoryResource;
use App\Models\Category;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RestoreCategoryAction
{
    public function handle(Category $category): JsonResponse
    {
        if (! $category->trashed()) {
            return ApiResponse::error(__('category.not_trashed'), [], 422);
        }

        // لا يُسترجَع تصنيف فرعي بينما أبوه محذوف/مفقود (تفادي يُتْم الشجرة).
        if ($category->parent_id !== null) {
            $parent = Category::withTrashed()->find($category->parent_id);
            if ($parent === null || $parent->trashed()) {
                return ApiResponse::error(__('category.restore_parent_first'), [], 422);
            }
        }

        $category->restore();

        Cache::tags(['categories'])->flush();
        FrontendRevalidate::tags(FrontendCacheTags::category($category));

        return ApiResponse::success(
            __('category.restored'),
            new CategoryResource($category->fresh())
        );
    }
}
