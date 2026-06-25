<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Jobs\DispatchCampaignJob;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Support\CampaignTransitionException;
use App\Modules\Notifications\Support\NotificationQueues;

/**
 * يوافق على حملة مسوّدة (mode=manual_approval) ⇒ Draft → {Scheduled إن كان موعدها مستقبليًّا |
 * Queued + إطلاق الإرسال}. ذرّيّ عبر UPDATE…WHERE status='draft' (يمنع موافقة مزدوجة).
 * المُوافِق + الوقت يُلتقطان في سجلّ التدقيق (تغيّر status).
 */
final class ApproveCampaignAction
{
    public function handle(NotificationCampaign $campaign): NotificationCampaign
    {
        if ($campaign->status !== CampaignStatus::Draft) {
            throw new CampaignTransitionException('لا يمكن الموافقة إلّا على حملة مسوّدة.');
        }

        $target = ($campaign->scheduled_at !== null && $campaign->scheduled_at->isFuture())
            ? CampaignStatus::Scheduled
            : CampaignStatus::Queued;

        $claimed = NotificationCampaign::query()
            ->where('id', $campaign->id)
            ->where('status', CampaignStatus::Draft->value)
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
