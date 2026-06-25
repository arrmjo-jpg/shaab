<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Models\AdCampaign;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف نهائيّ لحملة — يتسلسل (cascadeOnDelete) إلى الإبداعات ثم الإسنادات. لذا يُحلّ
 * الإبطال ويُفرَّغ قبل الحذف بينما العلاقات قائمة (بعده تختفي المساحات المرتبطة فيتعذّر
 * استنتاجها). لا يمكن استرجاعه.
 */
class ForceDeleteAdCampaignAction
{
    public function handle(AdCampaign $campaign): JsonResponse
    {
        // الإبطال أولاً: العلاقات (إبداعات→إسنادات→مساحات) تُحلّ الآن، وتختفي بالتسلسل بعد الحذف.
        AdServingInvalidator::forCampaign($campaign);

        $campaign->forceDelete();

        return ApiResponse::success(__('ads.campaign.force_deleted'));
    }
}
