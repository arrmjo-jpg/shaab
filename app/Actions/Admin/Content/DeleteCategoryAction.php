<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Category;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeleteCategoryAction
{
    public function handle(Category $category): JsonResponse
    {
        // لا يُحذف تصنيف يملك أبناء — تفادي يُتْم الشجرة
        if ($category->children()->exists()) {
            return ApiResponse::error(__('category.has_children'), [], 422);
        }

        $category->delete(); // soft delete

        Cache::tags(['categories'])->flush();
        FrontendRevalidate::tags(FrontendCacheTags::category($category));

        return ApiResponse::success(__('category.deleted'));
    }
}
