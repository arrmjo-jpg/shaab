<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Models\AdPlacement;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * فصل إسناد — حذف صلب (لا حذف ناعم على الإسنادات؛ هي روابط، والإبداع/المساحة يبقيان).
 * يُلتقَط مفتاح المساحة قبل الحذف ثم تُبطَل بِركتها.
 */
class DetachAdPlacementAction
{
    public function handle(AdPlacement $placement): JsonResponse
    {
        $zoneKey = $placement->zone->key;

        $placement->delete();

        AdServingInvalidator::flushZones([$zoneKey]);

        return ApiResponse::success(__('ads.placement.detached'));
    }
}
