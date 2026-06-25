<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Http\Resources\Admin\Advertising\AdPlacementResource;
use App\Models\AdPlacement;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تعديل إسناد (وزن/نشاط/أهليّة جهاز). يمسّ أهليّة العرض ⇒ إبطال صريح لبِركة المساحة.
 * الزوج (إبداع، مساحة) غير قابل للتغيير هنا (يُفرَض في الطلب).
 */
class UpdateAdPlacementAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(AdPlacement $placement, array $data): JsonResponse
    {
        $placement->update($data);

        $placement->loadMissing(['creative:id,title,type,weight', 'zone:id,key,name,placement_type']);

        AdServingInvalidator::flushZones([$placement->zone->key]);

        return ApiResponse::success(__('ads.placement.updated'), new AdPlacementResource($placement));
    }
}
