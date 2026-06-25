<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoCategoryResource;
use App\Models\VideoCategory;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * شجرة تصنيفات الفيديو الإدارية — مفلترة بلغة اختيارية، مرتّبة (sort_order, id)،
 * مع عدّاد الفيديوهات لكل عقدة. تُبنى الشجرة في الذاكرة (عدد التصنيفات صغير).
 */
class ListVideoCategoriesAction
{
    public function handle(): JsonResponse
    {
        $locale = (string) request()->query('locale', '');

        $all = VideoCategory::query()
            ->when(in_array($locale, VideoCategory::LOCALES, true), fn ($q) => $q->where('locale', $locale))
            ->withCount('videos')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // تجميع الأبناء حسب parent_id وبناء الجذور.
        $byParent = $all->groupBy('parent_id');

        $attachChildren = function (VideoCategory $node) use (&$attachChildren, $byParent): void {
            $children = $byParent->get($node->id, collect());
            $children->each($attachChildren);
            $node->setRelation('children', $children->values());
        };

        $roots = $byParent->get(null, collect())->values();
        $roots->each($attachChildren);

        return ApiResponse::success(
            data: VideoCategoryResource::collection($roots)->resolve()
        );
    }
}
