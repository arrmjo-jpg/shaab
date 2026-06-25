<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Http\Resources\Admin\Advertising\AdPlacementResource;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdZone;
use App\Support\Advertising\AdPlacementCompatibility;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إسناد إبداع إلى مساحة. يفرض قيدين إعداديين صريحين:
 *   (1) التوافق: نوع الإبداع متوافق مع نوع المساحة (AdPlacementCompatibility)،
 *   (2) منع التكرار: إسناد واحد لكل زوج (القيد الفريد في القاعدة هو الضمان النهائيّ).
 * كلاهما يُعيد ApiResponse 422 (لا استثناءات — سياسة AlphaCMS). إسناد جديد قد يدخل بِركة
 * المساحة ⇒ إبطال صريح لها.
 */
class AttachAdPlacementAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(array $data): JsonResponse
    {
        $creative = AdCreative::findOrFail($data['ad_creative_id']);
        $zone = AdZone::findOrFail($data['ad_zone_id']);

        if (! AdPlacementCompatibility::isCompatible($zone->placement_type, $creative->type)) {
            return ApiResponse::error(__('ads.placement.incompatible_type'), [], 422);
        }

        $duplicate = AdPlacement::query()
            ->where('ad_creative_id', $creative->id)
            ->where('ad_zone_id', $zone->id)
            ->exists();

        if ($duplicate) {
            return ApiResponse::error(__('ads.placement.duplicate'), [], 422);
        }

        $placement = AdPlacement::create([
            'ad_creative_id' => $creative->id,
            'ad_zone_id' => $zone->id,
            'weight' => $data['weight'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'device_targets' => $data['device_targets'] ?? null,
        ]);

        AdServingInvalidator::flushZones([$zone->key]);

        // تفادي إعادة الاستعلام: نربط النماذج المعروفة قبل التحويل لمورد.
        $placement->setRelation('creative', $creative);
        $placement->setRelation('zone', $zone);

        return ApiResponse::success(__('ads.placement.attached'), new AdPlacementResource($placement), 201);
    }
}
