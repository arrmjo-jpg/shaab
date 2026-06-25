<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Models\VideoCategory;
use App\Support\Cache\VideoCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * حذف نهائي لتصنيف فيديو. فيديوهات التصنيف تُفصَل (video_category_id = null) عبر
 * قيد المفتاح الأجنبي nullOnDelete — لا تُحذَف الفيديوهات.
 */
class ForceDeleteVideoCategoryAction
{
    public function handle(VideoCategory $category): JsonResponse
    {
        $category->forceDelete();

        Cache::tags([VideoCacheTags::ALL])->flush();

        return ApiResponse::success(__('video_category.force_deleted'));
    }
}
