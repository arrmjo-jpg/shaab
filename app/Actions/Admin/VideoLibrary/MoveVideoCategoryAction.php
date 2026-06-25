<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoCategoryResource;
use App\Models\VideoCategory;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * نقل تصنيف فيديو لأعلى/أسفل ضمن إخوته (نفس الأب + اللغة) — مرآة MoveCategoryAction.
 * يطبّع ترتيب المجموعة ثم يبدّل العنصر مع جاره: ترتيب حتمي مستقر.
 */
class MoveVideoCategoryAction
{
    public function handle(VideoCategory $category, string $direction): JsonResponse
    {
        $siblings = VideoCategory::query()
            ->where('parent_id', $category->parent_id)
            ->where('locale', $category->locale)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn (VideoCategory $c): bool => $c->id === $category->id);
        if ($index === false) {
            return ApiResponse::error(__('video_category.not_found'), [], 404);
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= $siblings->count()) {
            return ApiResponse::success(__('video_category.reordered'), new VideoCategoryResource($category->fresh()));
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

        // إبطال مُضيَّق: خلاصة اللغة + صفحات الإخوة المتأثّرة فقط (لا تفريغ شامل للنطاق).
        $tags = [VideoCacheTags::feed($category->locale)];
        foreach ($siblings as $sibling) {
            $tags[] = VideoCacheTags::category($category->locale, $sibling->slug);
        }
        $tags = array_values(array_unique($tags));
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(__('video_category.reordered'), new VideoCategoryResource($category->fresh()));
    }
}
