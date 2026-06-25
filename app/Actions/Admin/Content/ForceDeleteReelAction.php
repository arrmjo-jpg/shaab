<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Reel;
use App\Support\Cache\ReelCacheTags;
use App\Support\Content\ReelCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ForceDeleteReelAction
{
    public function handle(Reel $reel): JsonResponse
    {
        // reel_revisions تُحذف بالـ cascade؛ media_asset_id بـ nullOnDelete
        // (الأصل يبقى في المكتبة المركزية — حوكمة الوسائط مسؤولية منفصلة).
        // التقاط الوسوم/الروابط قبل الحذف (نحتاج locale/slug/id للإبطال).
        $tags = ReelCacheTags::invalidationTags($reel);
        ReelCdnPurge::purge($reel);

        $reel->forceDelete();

        Cache::tags($tags)->flush();

        return ApiResponse::success(__('reel.force_deleted'));
    }
}
