<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoCategoryResource;
use App\Models\VideoCategory;
use App\Support\Cache\VideoCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RestoreVideoCategoryAction
{
    public function handle(VideoCategory $category): JsonResponse
    {
        if (! $category->trashed()) {
            return ApiResponse::error(__('video_category.not_deleted'), [], 422);
        }

        $category->restore();
        Cache::tags([VideoCacheTags::ALL])->flush();

        return ApiResponse::success(
            __('video_category.restored'),
            new VideoCategoryResource($category->fresh())
        );
    }
}
