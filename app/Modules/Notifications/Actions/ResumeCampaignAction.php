<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Jobs\DispatchCampaignJob;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Support\CampaignTransitionException;
use App\Modules\Notifications\Support\NotificationQueues;

/**
 * يستأنف حملة موقوفة ⇒ Paused → {Scheduled إن كان موعدها مستقبليًّا | Queued + إعادة إطلاق الإرسال}.
 * آمن: fanOut يُطالب القنوات pending فقط، والتسليمات المُرسَلة مكرّرة-آمنة (insertOrIgnore).
 */
final class ResumeCampaignAction
{
    public function handle(NotificationCampaign $campaign): NotificationCampaign
    {
        if ($campaign->status !== CampaignStatus::Paused) {
            throw new CampaignTransitionException('لا يمكن الاستئناف إلّا لحملة موقوفة.');
        }

        $target = ($campaign->scheduled_at !== null && $campaign->scheduled_at->isFuture())
            ? CampaignStatus::Scheduled
            : CampaignStatus::Queued;

        $claimed = NotificationCampaign::query()
            ->where('id', $campaign->id)
            ->where('status', CampaignStatus::Paused->value)
            ->update(['status' => $target->value]);

        if ($claimed === 0) {
            throw new CampaignTransitionException('تغيّرت حالة الحملة بالتزامن.');
        }

        if ($target === CampaignStatus::Queued) {
            DispatchCampaignJob::dispatch((int) $campaign->id)
                ->onQueue(NotificationQueues::forPriority($campaign->priority));
        }

        return $campaign->refresh();
    }
}
