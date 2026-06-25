<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Models\AdCampaign;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف ناعم لحملة. الإبداعات تبقى (لا cascade على الحذف الناعم) فيُحلّ الإبطال صحيحاً؛
 * علاقة الإبداع→الحملة تستبعد المحذوف ناعماً تلقائياً فلا تظهر في بِركة المرشّحين بعد
 * إعادة البناء. الإبطال بعد الحذف يُسقط البِرَك المتأثّرة فوراً.
 */
class DeleteAdCampaignAction
{
    public function handle(AdCampaign $campaign): JsonResponse
    {
        $campaign->delete();

        AdServingInvalidator::forCampaign($campaign);

        return ApiResponse::success(__('ads.campaign.deleted'));
    }
}
