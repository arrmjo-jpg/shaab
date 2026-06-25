<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Http\Resources\Admin\Advertising\AdCampaignResource;
use App\Models\AdCampaign;
use App\Models\User;
use App\Support\Advertising\AdServingInvalidator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تعديل حملة (دون الحالة — تمرّ عبر مسار status). قد تتغيّر الأولوية/الوزن/النافذة الزمنية
 * ممّا يمسّ الأهليّة للعرض ⇒ إبطال صريح لبِرَك المساحات المتأثّرة (لا observer).
 */
class UpdateAdCampaignAction
{
    /** @param  array<string, mixed>  $data */
    public function handle(AdCampaign $campaign, array $data, User $actor): JsonResponse
    {
        $data['updated_by'] = $actor->id;

        $campaign->update($data);

        AdServingInvalidator::forCampaign($campaign);

        return ApiResponse::success(__('ads.campaign.updated'), new AdCampaignResource($campaign->fresh()));
    }
}
