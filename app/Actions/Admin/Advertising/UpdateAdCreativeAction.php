<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Enums\AdCreativeType;
use App\Http\Resources\Admin\Advertising\AdCreativeResource;
use App\Models\AdCreative;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تعديل إبداع. النوع الفعّال = المُرسَل أو القائم. html ⇒ يُفرَّغ الوسيط؛ image ⇒ يُفرَّغ
 * html_code. تنقية html_code (إن أُرسِل) تتمّ في مُحوّل النموذج AdCreative (حدّ دفاع، V8).
 * تغيّر النشاط/الوزن/الوسيط/الكود يمسّ العرض ⇒ إبطال صريح لبِرَك المساحات التي يظهر فيها.
 */
class UpdateAdCreativeAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(AdCreative $creative, array $data): JsonResponse
    {
        $type = $data['type'] ?? $creative->type->value;

        if ($type === AdCreativeType::Html->value) {
            $data['media_asset_id'] = null;
        } elseif ($type === AdCreativeType::Image->value) {
            $data['html_code'] = null;
        }

        $creative->update($data);

        AdServingInvalidator::forCreative($creative);

        return ApiResponse::success(__('ads.creative.updated'), new AdCreativeResource($creative->fresh()));
    }
}
