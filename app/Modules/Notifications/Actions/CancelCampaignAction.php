<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\CampaignChannelStatus;
use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Models\NotificationCampaignChannel;
use App\Modules\Notifications\Support\CampaignTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * يلغي حملة (أيّ حالة غير طرفيّة → Cancelled). يُعلّم القنوات pending → Skipped (سبب: ألغيت)؛
 * القنوات الجارية يوقفها حارس SendBatchJob (best-effort). ذرّيّ + معاملة. Cancelled طرفيّة (لا استئناف).
 */
final class CancelCampaignAction
{
    public function handle(NotificationCampaign $campaign): NotificationCampaign
    {
        $from = $campaign->status;

        if ($from->isTerminal()) {
            throw new CampaignTransitionException('الحملة في حالة طرفيّة بالفعل.');
        }

        return DB::transaction(function () use ($campaign, $from): NotificationCampaign {
            $claimed = NotificationCampaign::query()
                ->where('id', $campaign->id)
                ->where('status', $from->value)
                ->update(['status' => CampaignStatus::Cancelled->value, 'finished_at' => now()]);

            if ($claimed === 0) {
                throw new CampaignTransitionException('تغيّرت حالة الحملة بالتزامن.');
            }

            NotificationCampaignChannel::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', CampaignChannelStatus::Pending->value)
                ->update(['status' => CampaignChannelStatus::Skipped->value, 'skip_reason' => 'campaign cancelled']);

            return $campaign->refresh();
        });
    }
}
