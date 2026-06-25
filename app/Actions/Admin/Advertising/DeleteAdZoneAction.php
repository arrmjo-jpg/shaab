<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Models\AdZone;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف صلب محميّ بالاستخدام — لا soft delete للمساحات (كيان إعداد). يُرفَض الحذف
 * إن كانت المساحة تحوي إسنادات (يُفصَل أولاً) بإعادة ApiResponse لا استثناء.
 */
class DeleteAdZoneAction
{
    public function handle(AdZone $zone): JsonResponse
    {
        if ($zone->placements()->exists()) {
            return ApiResponse::error(__('ads.zone.has_placements'), [], 422);
        }

        $key = $zone->key;
        $zone->delete();
        AdServingInvalidator::flushZones([$key]);

        return ApiResponse::success(__('ads.zone.deleted'));
    }
}
