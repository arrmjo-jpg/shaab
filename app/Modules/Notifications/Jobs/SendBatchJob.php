<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Channels\ChannelDriverRegistry;
use App\Modules\Notifications\Enums\AddressingModel;
use App\Modules\Notifications\Enums\CampaignChannelStatus;
use App\Modules\Notifications\Enums\CampaignStatus;
use App\Modules\Notifications\Enums\DeepLinkType;
use App\Modules\Notifications\Enums\DeliveryStatus;
use App\Modules\Notifications\Models\MobileDevice;
use App\Modules\Notifications\Models\NotificationCampaign;
use App\Modules\Notifications\Models\NotificationCampaignChannel;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Support\CampaignCompletion;
use App\Modules\Notifications\Support\ChannelMessage;
use App\Modules\Notifications\Support\DeepLink;
use App\Modules\Notifications\Support\NotificationQueues;
use App\Modules\Notifications\Support\Recipient;
use App\Modules\Notifications\Support\RecipientBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * عامل إرسال دفعة — يُرسل دفعة واحدة (topic أو ≤500 توكن) عبر الدرايفر. idempotent: topic عبر claim
 * ذرّيّ (Cache::add)؛ per_recipient عبر deliveries (unique channel+recipient) + إرسال المعلّقين فقط
 * (إعادة التشغيل لا تُضاعف). يُقلّم التوكنات الميتة (is_active=false) ويُحدّث العدّادات ذرّيًّا.
 */
final class SendBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $channelId, public readonly RecipientBatch $batch)
    {
        $this->onQueue(NotificationQueues::DEFAULT);
    }

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ChannelDriverRegistry $drivers, CampaignCompletion $completion): void
    {
        $channel = NotificationCampaignChannel::query()->find($this->channelId);
        if ($channel === null || $channel->status !== CampaignChannelStatus::Sending) {
            return; // حارس حالة — لا إرسال خارج sending
        }

        $campaign = NotificationCampaign::query()->find($channel->campaign_id);
        if ($campaign === null || ! $drivers->has($channel->channel)) {
            return;
        }
        if ($campaign->status === CampaignStatus::Cancelled) {
            return; // الحملة أُلغيت — أوقف الدفعات غير المُبتدأة (إيقاف best-effort للجاري)
        }

        $driver = $drivers->for($channel->channel);
        $message = $this->message($channel);
        $idempotencyKey = "camp:{$campaign->id}:ch:{$channel->id}";

        if ($this->batch->mode === AddressingModel::Topic) {
            $this->sendTopic($driver, $message, $channel, $idempotencyKey);
        } else {
            $this->sendPerRecipient($driver, $message, $channel, (int) $campaign->id, $idempotencyKey);
        }

        $completion->checkChannel((int) $channel->id);
    }

    private function sendTopic(object $driver, ChannelMessage $message, NotificationCampaignChannel $channel, string $key): void
    {
        // claim ذرّيّ لكلّ قناة topic (نمط Broadcast) — إعادة التشغيل ⇒ تخطٍّ، لا نشر مزدوج.
        if (! Cache::add("notif:topic:{$channel->id}", true, now()->addHours(6))) {
            return;
        }

        $report = $driver->send($message, $this->batch, $key);

        if ($report->skipped) {
            // قناة غير متوفّرة وقت الإرسال (Gate B) — عُدّ topic كـskipped (يمنع التعليق الأبديّ).
            NotificationCampaignChannel::query()->where('id', $channel->id)
                ->update(['skipped' => DB::raw('skipped + 1')]);

            return;
        }

        NotificationCampaignChannel::query()->where('id', $channel->id)->update([
            'sent' => DB::raw('sent + '.$report->sent),
            'failed' => DB::raw('failed + '.$report->failed),
            'provider_ref' => $report->providerRef,
        ]);
    }

    private function sendPerRecipient(object $driver, ChannelMessage $message, NotificationCampaignChannel $channel, int $campaignId, string $key): void
    {
        $now = now();
        $rows = array_map(fn (Recipient $r): array => [
            'campaign_channel_id' => $channel->id,
            'campaign_id' => $campaignId,
            'channel' => $channel->channel->value,
            'recipient_type' => str_starts_with($r->ref, 'device:') ? 'device' : (str_starts_with($r->ref, 'user:') ? 'user' : 'contact'),
            'recipient_id' => $r->ref,
            'address_snapshot' => $r->address,
            'status' => DeliveryStatus::Pending->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->batch->recipients);
        NotificationDelivery::query()->insertOrIgnore($rows); // idempotent عبر unique(channel,recipient)

        $refs = array_map(fn (Recipient $r): string => $r->ref, $this->batch->recipients);
        $pending = NotificationDelivery::query()
            ->where('campaign_channel_id', $channel->id)
            ->whereIn('recipient_id', $refs)
            ->where('status', DeliveryStatus::Pending->value)
            ->pluck('address_snapshot', 'recipient_id');
        if ($pending->isEmpty()) {
            return; // الكلّ مُرسَل سابقًا (إعادة تشغيل)
        }

        $batch = RecipientBatch::forRecipients(
            $pending->map(fn ($address, $ref): Recipient => new Recipient((string) $ref, (string) $address))->values()->all(),
        );

        $report = $driver->send($message, $batch, $key);
        if ($report->skipped) {
            // قناة غير متوفّرة وقت الإرسال (Gate B) — علّم المعلّقين skipped وعُدّهم (يمنع التعليق الأبديّ).
            NotificationDelivery::query()
                ->where('campaign_channel_id', $channel->id)
                ->whereIn('recipient_id', $pending->keys()->all())
                ->where('status', DeliveryStatus::Pending->value)
                ->update(['status' => DeliveryStatus::Skipped->value, 'error' => 'channel unavailable at send']);
            NotificationCampaignChannel::query()->where('id', $channel->id)
                ->update(['skipped' => DB::raw('skipped + '.$pending->count())]);

            return;
        }

        foreach ($report->results as $result) {
            $status = $result->invalid
                ? DeliveryStatus::Invalid
                : ($result->ok ? DeliveryStatus::Sent : DeliveryStatus::Failed);
            NotificationDelivery::query()
                ->where('campaign_channel_id', $channel->id)
                ->where('recipient_id', $result->ref)
                ->update([
                    'status' => $status->value,
                    'provider_message_id' => $result->providerMessageId,
                    'error' => $result->error,
                    'sent_at' => $result->ok ? now() : null,
                ]);
        }

        $this->pruneInvalid($report->invalidRefs());

        NotificationCampaignChannel::query()->where('id', $channel->id)->update([
            'sent' => DB::raw('sent + '.$report->sent),
            'failed' => DB::raw('failed + '.$report->failed),
            'invalid' => DB::raw('invalid + '.$report->invalid),
        ]);
    }

    /** @param  array<int,string>  $refs */
    private function pruneInvalid(array $refs): void
    {
        $deviceIds = [];
        foreach ($refs as $ref) {
            if (str_starts_with($ref, 'device:')) {
                $deviceIds[] = (int) substr($ref, 7);
            }
        }
        if ($deviceIds !== []) {
            MobileDevice::query()->whereIn('id', $deviceIds)->update(['is_active' => false]);
        }
    }

    private function message(NotificationCampaignChannel $channel): ChannelMessage
    {
        // snapshot القناة المُصيَّر (render تمّ مرّة واحدة عند الإنشاء) — لا re-render هنا ⇒ immutable.
        $content = is_array($channel->content) ? $channel->content : [];
        $type = DeepLinkType::tryFrom((string) ($content['deep_link_type'] ?? 'none')) ?? DeepLinkType::None;
        $deepLink = $type !== DeepLinkType::None
            ? DeepLink::to($type, isset($content['deep_link_value']) ? (string) $content['deep_link_value'] : null)
            : null;

        return new ChannelMessage(
            title: (string) ($content['title'] ?? ''),
            body: (string) ($content['body'] ?? ''),
            imageUrl: isset($content['image_url']) ? (string) $content['image_url'] : null,
            deepLink: $deepLink,
        );
    }
}
