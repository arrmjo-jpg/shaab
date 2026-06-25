<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Models\AdCampaign;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * استرجاع حملة محذوفة ناعماً. تعود بحالتها السابقة؛ إن كانت قابلة للعرض فقد تُعاد إلى
 * بِركة المرشّحين ⇒ إبطال دفاعيّ للمساحات المتأثّرة كي تُعاد البناء بالحالة الجديدة.
 */
class RestoreAdCampaignAction
{
    public function handle(AdCampaign $campaign): JsonResponse
    {
        $campaign->restore();

        AdServingInvalidator::forCampaign($campaign);

        return ApiResponse::success(__('ads.campaign.restored'));
    }
}
