<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Models\Video;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * حذف ناعم لفيديو (قابل للاسترجاع). الأصل لا يُمَسّ — يُنظَّف فقط عند الحذف النهائي.
 */
class DeleteVideoAction
{
    public function handle(Video $video): JsonResponse
    {
        $video->loadMissing('category');
        $video->delete();

        $tags = VideoCacheTags::invalidationTags($video, categorySlug: $video->category?->slug);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(__('video.deleted'));
    }
}
