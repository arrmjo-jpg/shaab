<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Models\Category;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ForceDeleteCategoryAction
{
    public function handle(Category $category): JsonResponse
    {
        // FK: primary_category_id مقيَّد بـ restrictOnDelete — يُمنع الحذف النهائي
        // ما دام التصنيف رئيسياً لأي خبر (حتى المحذوف ناعماً). الروابط الثانوية
        // (article_category) تُنظَّف تلقائياً بالـ cascade.
        $stillPrimary = Article::withTrashed()
            ->where('primary_category_id', $category->id)
            ->exists();

        if ($stillPrimary) {
            return ApiResponse::error(__('category.force_delete_has_articles'), [], 422);
        }

        $category->forceDelete();

        Cache::tags(['categories'])->flush();
        FrontendRevalidate::tags(FrontendCacheTags::category($category));

        return ApiResponse::success(__('category.force_deleted'));
    }
}
