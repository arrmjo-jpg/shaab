<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Category;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حارس ثوابت هرمية التصنيف (ADR A1/A5 + قواعد اللغة).
 *
 * يتحقق من: وجود الأب، تطابق لغة الأب، منع الأب-الذاتي، منع الدورات،
 * وعدم تجاوز العمق الأقصى الثابت (Category::MAX_DEPTH = 3).
 *
 * يُرجع JsonResponse عند الرفض، أو null إذا كان الإجراء سليماً.
 * (نمط مطابق لـ RoleEscalationGuard — لا service عام، لا observer.)
 */
final class CategoryHierarchyGuard
{
    /**
     * @param  Category|null  $node  العقدة قيد التعديل (null عند الإنشاء)
     */
    public static function check(
        ?Category $node,
        ?int $parentId,
        string $locale
    ): ?JsonResponse {
        if ($parentId === null) {
            // جذر — العمق 1، لكن يجب ألا يتجاوز عمق الشجرة الأقصى عند النقل
            return self::checkSubtreeDepth($node, 1);
        }

        // منع الأب-الذاتي
        if ($node !== null && $parentId === $node->id) {
            return ApiResponse::error(__('category.self_parent'), [], 422);
        }

        $parent = Category::query()->find($parentId);
        if ($parent === null) {
            return ApiResponse::error(__('category.parent_not_found'), [], 422);
        }

        // تطابق اللغة (ADR A3.4 — لا إسناد عبر اللغات)
        if ($parent->locale !== $locale) {
            return ApiResponse::error(__('category.parent_locale_mismatch'), [], 422);
        }

        // منع الدورات: الأب الجديد يجب ألا يكون من نسل العقدة
        if ($node !== null && $node->isAncestorOf($parent->id)) {
            return ApiResponse::error(__('category.circular_hierarchy'), [], 422);
        }

        // إنفاذ العمق الأقصى الثابت
        $parentDepth = $parent->depth();
        if ($parentDepth >= Category::MAX_DEPTH) {
            return ApiResponse::error(__('category.max_depth_exceeded'), [], 422);
        }

        return self::checkSubtreeDepth($node, $parentDepth + 1);
    }

    /**
     * يضمن أن عمق العقدة الجديد + امتداد شجرتها لا يتجاوز MAX_DEPTH.
     */
    private static function checkSubtreeDepth(?Category $node, int $newOwnDepth): ?JsonResponse
    {
        $subtree = $node !== null ? $node->subtreeDepth() : 1;

        if (($newOwnDepth + $subtree - 1) > Category::MAX_DEPTH) {
            return ApiResponse::error(__('category.max_depth_exceeded'), [], 422);
        }

        return null;
    }
}
