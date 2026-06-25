<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Channels\ChannelBinderRegistry;
use App\Modules\Notifications\Enums\CampaignChannelStatus;
use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Models\NotificationCampaignChannel;
use App\Modules\Notifications\Support\AudienceResult;
use App\Modules\Notifications\Support\CampaignCompletion;
use App\Modules\Notifications\Support\NotificationQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * منسّق إرسال حملة — **حلّ الجمهور هنا لا في الـDispatcher**. ينتقل ذرّيًّا Queued→Sending، ثمّ لكلّ
 * قناة pending: ChannelBinder يبثّ دفعات (lazyById، ذاكرة ثابتة) ⇒ SendBatchJob لكلّ دفعة + يضبط
 * targeted. صفر materialization في الذاكرة (الدفعات تُبثّ وتُطلَق). $tries=1 (لا queue storm).
 */
final class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue(NotificationQueues::DEFAULT);
    }

    public function handle(ChannelBinderRegistry $binders, CampaignCompletion $completion): void
    {
        $campaign = NotificationCampaign::query()->find($this->campaignId);
        if ($campaign === null) {
            return;
        }

        // انتقال ذرّيّ Queued → Sending (يمنع تنفيذًا مزدوجًا).
        $claimed = NotificationCampaign::query()
            ->where('id', $campaign->id)
            ->where('status', CampaignStatus::Queued->value)
            ->update(['status' => CampaignStatus::Sending->value, 'started_at' => now()]);
        if ($claimed === 0) {
            return;
        }

        $audience = AudienceResult::fromArray(is_array($campaign->audience_spec) ? $campaign->audience_spec : ['type' => 'all']);

        foreach ($campaign->channels()->where('status', CampaignChannelStatus::Pending->value)->get() as $channel) {
            $this->fanOut($channel, $audience, $binders, $completion);
        }

        $completion->maybeCompleteCampaign((int) $campaign->id);
    }

    private function fanOut(NotificationCampaignChannel $channel, AudienceResult $audience, ChannelBinderRegistry $binders, CampaignCompletion $completion): void
    {
        $binder = $binders->for($channel->channel);
        if ($binder === null) {
            NotificationCampaignChannel::query()->where('id', $channel->id)->where('status', CampaignChannelStatus::Pending->value)
                ->update(['status' => CampaignChannelStatus::Skipped->value, 'skip_reason' => 'no binder']);

            return;
        }

        $claimed = NotificationCampaignChannel::query()->where('id', $channel->id)->where('status', CampaignChannelStatus::Pending->value)
            ->update(['status' => CampaignChannelStatus::Sending->value]);
        if ($claimed === 0) {
            return;
        }

        $targeted = 0;
        foreach ($binder->bind($audience) as $batch) {
            SendBatchJob::dispatch((int) $channel->id, $batch)->onQueue(NotificationQueues::DEFAULT);
            $targeted += $batch->count();
        }

        NotificationCampaignChannel::query()->where('id', $channel->id)->update(['targeted' => $targeted]);

        if ($targeted === 0) {
            NotificationCampaignChannel::query()->where('id', $channel->id)->where('status', CampaignChannelStatus::Sending->value)
                ->update(['status' => CampaignChannelStatus::Completed->value]);
            $completion->maybeCompleteCampaign((int) $channel->campaign_id);

            return;
        }

        // حارس سباق: قد تنتهي الدفعات قبل ضبط targeted.
        $completion->checkChannel((int) $channel->id);
    }
}
