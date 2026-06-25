<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Actions\Admin\Media\ReprocessMediaAssetAction;
use App\Http\Resources\Admin\VideoLibrary\VideoResource;
use App\Models\Video;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إعادة معالجة وسائط فيديو مرفوع (retry) عبر الخط القائم — تُتاح فقط للأصل المرفوع
 * (الخارجي لا يُعالَج). تعيد الأصل إلى queued وتُجدوِل الترميز.
 */
class ReprocessVideoMediaAction
{
    public function handle(Video $video): JsonResponse
    {
        $asset = $video->mediaAsset;

        if ($asset === null || ! $asset->isUploadedVideo()) {
            return ApiResponse::error(__('video.reprocess_unavailable'), [], 422);
        }

        (new ReprocessMediaAssetAction)->handle($asset);

        return ApiResponse::success(
            __('video.reprocess_queued'),
            new VideoResource($video->fresh()->load(['author:id,name', 'mediaAsset', 'category']))
        );
    }
}
