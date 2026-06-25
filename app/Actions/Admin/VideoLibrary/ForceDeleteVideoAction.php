<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Models\Video;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use App\Support\Video\VideoMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * حذف نهائي — لا استرجاع. ينظّف الأصل المرفوع المملوك (1:1، غير المُشترَك) عبر
 * VideoMedia::releaseOwnedAsset؛ الأصول الخارجية المُشتركة لا تُمَسّ.
 */
class ForceDeleteVideoAction
{
    public function handle(Video $video): JsonResponse
    {
        $video->loadMissing(['category', 'mediaAsset']);
        $categorySlug = $video->category?->slug;

        // تنظيف الأصل المملوك قبل إزالة الصفّ (يتجنّب أصلاً يتيماً).
        VideoMedia::releaseOwnedAsset($video);

        $video->forceDelete();

        $tags = VideoCacheTags::invalidationTags($video, categorySlug: $categorySlug);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(__('video.force_deleted'));
    }
}
