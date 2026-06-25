<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\CampaignChannelStatus;
use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Models\NotificationCampaignChannel;

/**
 * اشتقاق إكمال الحملة (آلة الحالتين) — انتقالات ذرّيّة UPDATE…WHERE status=expected. يُستدعى من
 * الـjobs. القناة تكتمل عند بلوغ المعالَج للمستهدف؛ الحملة تُشتقّ من قنواتها (Completed/Partially/Failed).
 */
final class CampaignCompletion
{
    /** القناة: sending → completed|failed عند بلوغ (sent+failed+invalid+skipped) ≥ targeted. */
    public function checkChannel(int $channelId): void
    {
        $channel = NotificationCampaignChannel::query()->find($channelId);
        if ($channel === null || $channel->status !== CampaignChannelStatus::Sending) {
            return;
        }

        $processed = $channel->sent + $channel->failed + $channel->invalid + $channel->skipped;
        if ($channel->targeted <= 0 || $processed < $channel->targeted) {
            return;
        }

        $final = $channel->sent > 0 ? CampaignChannelStatus::Completed : CampaignChannelStatus::Failed;
        $claimed = NotificationCampaignChannel::query()
            ->where('id', $channelId)
            ->where('status', CampaignChannelStatus::Sending->value)
            ->update(['status' => $final->value]);

        if ($claimed === 1) {
            $this->maybeCompleteCampaign((int) $channel->campaign_id);
        }
    }

    /** الحملة: sending → {Completed|PartiallyCompleted|Failed} عند بلوغ كلّ القنوات حالةً طرفيّة. */
    public function maybeCompleteCampaign(int $campaignId): void
    {
        $campaign = NotificationCampaign::query()->find($campaignId);
        if ($campaign === null || $campaign->status !== CampaignStatus::Sending) {
            return;
        }

        $channels = NotificationCampaignChannel::query()->where('campaign_id', $campaignId)->get(['status']);
        $terminal = [CampaignChannelStatus::Completed, CampaignChannelStatus::Failed, CampaignChannelStatus::Skipped, CampaignChannelStatus::Superseded];

        if (! $channels->every(fn (NotificationCampaignChannel $c): bool => in_array($c->status, $terminal, true))) {
            return; // قنوات ما تزال جارية
        }

        $anyCompleted = $channels->contains(fn (NotificationCampaignChannel $c): bool => $c->status === CampaignChannelStatus::Completed);
        $anyNot = $channels->contains(fn (NotificationCampaignChannel $c): bool => $c->status !== CampaignChannelStatus::Completed);

        $final = match (true) {
            $anyCompleted && $anyNot => CampaignStatus::PartiallyCompleted,
            $anyCompleted => CampaignStatus::Completed,
            default => CampaignStatus::Failed,
        };

        NotificationCampaign::query()
            ->where('id', $campaignId)
            ->where('status', CampaignStatus::Sending->value)
            ->update(['status' => $final->value, 'finished_at' => now()]);
    }
}
