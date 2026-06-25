<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\ReelResource;
use App\Models\Reel;
use App\Support\Cache\ReelCacheTags;
use App\Support\Content\ReelCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RestoreReelAction
{
    public function handle(Reel $reel): JsonResponse
    {
        if (! $reel->trashed()) {
            return ApiResponse::error(__('reel.not_trashed'), [], 422);
        }

        $reel->restore();

        Cache::tags(ReelCacheTags::invalidationTags($reel))->flush();
        ReelCdnPurge::purge($reel);

        return ApiResponse::success(
            __('reel.restored'),
            new ReelResource($reel->fresh())
        );
    }
}
