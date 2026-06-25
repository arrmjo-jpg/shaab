<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Enums\CampaignChannelStatus;
use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Enums\DeliveryStatus;
use App\Modules\Notifications\Enums\TrackingMode;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Models\NotificationCampaignChannel;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Support\CampaignCompletion;

/**
 * مصالحة الحملات العالقة — شبكة الأمان: سلامة النظام لا تعتمد على نجاح كلّ الـjobs. أيّ حملة
 * Sending أقدم من العتبة تُجبَر على الإكمال: القنوات per_recipient تُعاد حساباتها من حقيقة
 * deliveries (المعلّق ⇒ failed)، وقنوات topic تُكمَل من العدّادات. ثمّ تُشتقّ حالة الحملة. ⇒ **لا
 * حالة Sending أبديّة**. idempotent (انتقالات ذرّيّة WHERE status='sending').
 */
final class ReconcileStuckCampaignsAction
{
    public function __construct(private readonly CampaignCompletion $completion) {}

    public function handle(int $stuckMinutes = 120): int
    {
        $reconciled = 0;

        NotificationCampaign::query()
            ->where('status', CampaignStatus::Sending->value)
            ->where('started_at', '<=', now()->subMinutes($stuckMinutes))
            ->select(['id'])
            ->chunkById(100, function ($campaigns) use (&$reconciled): void {
                foreach ($campaigns as $campaign) {
                    $this->reconcile((int) $campaign->id);
                    $reconciled++;
                }
            });

        return $reconciled;
    }

    private function reconcile(int $campaignId): void
    {
        NotificationCampaignChannel::query()
            ->where('campaign_id', $campaignId)
            ->where('status', CampaignChannelStatus::Sending->value)
            ->get()
            ->each(fn (NotificationCampaignChannel $channel) => $this->reconcileChannel($channel));

        $this->completion->maybeCompleteCampaign($campaignId);
    }

    private function reconcileChannel(NotificationCampaignChannel $channel): void
    {
        if ($channel->tracking_mode === TrackingMode::PerRecipient) {
            // المعلّقون (jobs لم تكتمل) ⇒ failed، ثمّ أعِد حساب العدّادات من حقيقة deliveries.
            NotificationDelivery::query()
                ->where('campaign_channel_id', $channel->id)
                ->where('status', DeliveryStatus::Pending->value)
                ->update(['status' => DeliveryStatus::Failed->value, 'error' => 'reconciled: batch did not complete']);

            $counts = NotificationDelivery::query()
                ->where('campaign_channel_id', $channel->id)
                ->selectRaw('status, count(*) as n')
                ->groupBy('status')
                ->pluck('n', 'status');

            $sent = (int) ($counts[DeliveryStatus::Sent->value] ?? 0) + (int) ($counts[DeliveryStatus::Delivered->value] ?? 0);

            NotificationCampaignChannel::query()
                ->where('id', $channel->id)
                ->where('status', CampaignChannelStatus::Sending->value)
                ->update([
                    'sent' => $sent,
                    'failed' => (int) ($counts[DeliveryStatus::Failed->value] ?? 0),
                    'invalid' => (int) ($counts[DeliveryStatus::Invalid->value] ?? 0),
                    'skipped' => (int) ($counts[DeliveryStatus::Skipped->value] ?? 0),
                    'status' => ($sent > 0 ? CampaignChannelStatus::Completed : CampaignChannelStatus::Failed)->value,
                ]);

            return;
        }

        // topic/aggregate — أكمل من العدّادات؛ لا تقدّم ⇒ Failed.
        NotificationCampaignChannel::query()
            ->where('id', $channel->id)
            ->where('status', CampaignChannelStatus::Sending->value)
            ->update(['status' => ($channel->sent > 0 ? CampaignChannelStatus::Completed : CampaignChannelStatus::Failed)->value]);
    }
}
