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
use Illuminate\Support\Facades\DB;

/**
 * نقل تصنيف لأعلى/أسفل ضمن إخوته (نفس الأب + نفس اللغة). يطبّع ترتيب
 * المجموعة بالكامل (sort_order = الفهرس) ليحسم أي تساوٍ، ثم يبدّل العنصر مع
 * جاره المباشر — فالنتيجة ترتيب حتمي مستقر مهما كانت القيم السابقة.
 */
class MoveCategoryAction
{
    public function handle(Category $category, string $direction): JsonResponse
    {
        // الإخوة بنفس ترتيب القائمة (sort_order ثم id).
        $siblings = Category::query()
            ->where('parent_id', $category->parent_id)
            ->where('locale', $category->locale)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn (Category $c): bool => $c->id === $category->id);
        if ($index === false) {
            return ApiResponse::error(__('category.not_found'), [], 404);
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        // أصلاً في الطرف — لا تغيير (نجاح صامت).
        if ($target < 0 || $target >= $siblings->count()) {
            return ApiResponse::success(
                __('category.reordered'),
                new CategoryResource($category->fresh())
            );
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $position => $node) {
                if ($node->sort_order !== $position) {
                    $node->forceFill(['sort_order' => $position])->save();
                }
            }
        });

        Cache::tags(['categories'])->flush();
        FrontendRevalidate::tags(FrontendCacheTags::category($category));

        return ApiResponse::success(
            __('category.reordered'),
            new CategoryResource($category->fresh())
        );
    }
}
