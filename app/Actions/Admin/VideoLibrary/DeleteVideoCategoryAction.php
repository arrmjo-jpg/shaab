<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Models\VideoCategory;
use App\Support\Cache\VideoCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeleteVideoCategoryAction
{
    public function handle(VideoCategory $category): JsonResponse
    {
        $category->delete();

        Cache::tags([VideoCacheTags::ALL])->flush();

        return ApiResponse::success(__('video_category.deleted'));
    }
}
