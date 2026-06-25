<?php

declare(strict_types=1);

namespace App\Support\Video;

use App\Models\VideoCategory;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حارس ثوابت هرمية تصنيف الفيديو — مرآة CategoryHierarchyGuard. يتحقّق من: وجود
 * الأب، تطابق لغة الأب، منع الأب-الذاتي، منع الدورات، وعدم تجاوز العمق الأقصى
 * (VideoCategory::MAX_DEPTH). يُرجع JsonResponse عند الرفض أو null عند السلامة.
 */
final class VideoCategoryHierarchyGuard
{
    public static function check(?VideoCategory $node, ?int $parentId, string $locale): ?JsonResponse
    {
        if ($parentId === null) {
            return self::checkSubtreeDepth($node, 1);
        }

        if ($node !== null && $parentId === $node->id) {
            return ApiResponse::error(__('video_category.self_parent'), [], 422);
        }

        $parent = VideoCategory::query()->find($parentId);
        if ($parent === null) {
            return ApiResponse::error(__('video_category.parent_not_found'), [], 422);
        }

        if ($parent->locale !== $locale) {
            return ApiResponse::error(__('video_category.parent_locale_mismatch'), [], 422);
        }

        if ($node !== null && $node->isAncestorOf($parent->id)) {
            return ApiResponse::error(__('video_category.circular_hierarchy'), [], 422);
        }

        $parentDepth = $parent->depth();
        if ($parentDepth >= VideoCategory::MAX_DEPTH) {
            return ApiResponse::error(__('video_category.max_depth_exceeded'), [], 422);
        }

        return self::checkSubtreeDepth($node, $parentDepth + 1);
    }

    private static function checkSubtreeDepth(?VideoCategory $node, int $newOwnDepth): ?JsonResponse
    {
        $subtree = $node !== null ? $node->subtreeDepth() : 1;

        if (($newOwnDepth + $subtree - 1) > VideoCategory::MAX_DEPTH) {
            return ApiResponse::error(__('video_category.max_depth_exceeded'), [], 422);
        }

        return null;
    }
}
