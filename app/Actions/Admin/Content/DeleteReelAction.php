<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Reel;
use App\Support\Cache\ReelCacheTags;
use App\Support\Content\ReelCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeleteReelAction
{
    public function handle(Reel $reel): JsonResponse
    {
        $reel->delete(); // soft delete — يختفي من العام → إبطال كاش/حافة CDN

        Cache::tags(ReelCacheTags::invalidationTags($reel))->flush();
        ReelCdnPurge::purge($reel);

        return ApiResponse::success(__('reel.deleted'));
    }
}
