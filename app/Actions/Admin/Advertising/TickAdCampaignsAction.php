<?php

declare(strict_types=1);

namespace App\Actions\Admin\Advertising;

use App\Enums\AdCampaignStatus;
use App\Models\AdCampaign;
use App\Support\Advertising\AdCampaignLifecycle;
use App\Support\Advertising\AdServingInvalidator;
use Carbon\CarbonInterface;

/**
 * يطبّق انتقالات دورة الحياة التلقائية المستحقّة (المجدوَل) — التفعيل عند بدء النافذة،
 * والإكمال عند انتهائها أو فواتها. الحالات المُدارة آلياً فقط: scheduled/active.
 * idempotent: لا يلمس إلا ما استحقّ انتقالاً. يُبطل بِرَك الخدمة المتأثّرة فور كل انتقال.
 */
class TickAdCampaignsAction
{
    public function handle(?CarbonInterface $now = null): int
    {
        $now ??= now();

        $campaigns = AdCampaign::query()
            ->whereIn('status', [
                AdCampaignStatus::Scheduled->value,
                AdCampaignStatus::Active->value,
            ])
            ->get();

        $transitioned = 0;
        foreach ($campaigns as $campaign) {
            $target = AdCampaignLifecycle::autoTransitionFor($campaign, $now);
            if ($target === null) {
                continue;
            }

            $campaign->status = $target;
            $campaign->save();
            AdServingInvalidator::forCampaign($campaign);
            $transitioned++;
        }

        return $transitioned;
    }
}
