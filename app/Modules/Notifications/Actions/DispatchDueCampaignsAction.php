<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Jobs\DispatchCampaignJob;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Support\NotificationQueues;

/**
 * يُفعّل الحملات المجدولة المستحقّة — **انتقال حالة لا إنشاء** (الإنشاء حصرٌ على CampaignDispatcher).
 * Scheduled → Queued عبر UPDATE…WHERE status='scheduled' (affected=1 ⇒ ملكيّة، يمنع سباق العمّال).
 */
final class DispatchDueCampaignsAction
{
    public function handle(): int
    {
        $dispatched = 0;

        NotificationCampaign::query()
            ->where('status', CampaignStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->select(['id', 'priority'])
            ->chunkById(200, function ($campaigns) use (&$dispatched): void {
                foreach ($campaigns as $campaign) {
                    $claimed = NotificationCampaign::query()
                        ->where('id', $campaign->id)
                        ->where('status', CampaignStatus::Scheduled->value)
                        ->update(['status' => CampaignStatus::Queued->value]);

                    if ($claimed === 1) {
                        DispatchCampaignJob::dispatch((int) $campaign->id)
                            ->onQueue(NotificationQueues::forPriority($campaign->priority));
                        $dispatched++;
                    }
                }
            });

        return $dispatched;
    }
}
