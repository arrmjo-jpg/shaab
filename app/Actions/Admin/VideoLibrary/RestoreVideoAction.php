<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoResource;
use App\Models\Video;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RestoreVideoAction
{
    public function handle(Video $video): JsonResponse
    {
        if (! $video->trashed()) {
            return ApiResponse::error(__('video.not_deleted'), [], 422);
        }

        $video->restore();
        $video->load('category');

        $tags = VideoCacheTags::invalidationTags($video, categorySlug: $video->category?->slug);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(
            __('video.restored'),
            new VideoResource($video->fresh()->load(['author:id,name', 'mediaAsset', 'category']))
        );
    }
}
