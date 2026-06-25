<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Enums\AdCreativeType;
use App\Http\Resources\Admin\Advertising\AdCreativeResource;
use App\Models\AdCreative;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إنشاء إبداع. html ⇒ يُفرَّغ الوسيط؛ image ⇒ يُفرَّغ html_code (سلامة عبر-الحقول).
 * تنقية html_code (HTMLPurifier) تتمّ في مُحوّل النموذج AdCreative (حدّ دفاع، V8) فتشمل
 * كلّ مسارات الكتابة. إبداع جديد بلا إسنادات بعد ⇒ لا حاجة لإبطال بِرَك الخدمة.
 */
class CreateAdCreativeAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(array $data): JsonResponse
    {
        if ($data['type'] === AdCreativeType::Html->value) {
            $data['media_asset_id'] = null;
        } elseif ($data['type'] === AdCreativeType::Image->value) {
            $data['html_code'] = null;
        }

        $creative = AdCreative::create($data);

        // fresh(): يضمن تحميل uuid/الأعمدة الافتراضية.
        return ApiResponse::success(__('ads.creative.created'), new AdCreativeResource($creative->fresh()), 201);
    }
}
