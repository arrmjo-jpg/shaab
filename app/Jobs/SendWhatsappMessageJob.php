<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WhatsappCampaignStatus;
use App\Enums\WhatsappMediaType;
use App\Enums\WhatsappMessageStatus;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignMessage;
use App\Support\Whatsapp\UltraMsgClient;
use App\Support\Whatsapp\WhatsappCampaignContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * إرسال رسالة واتساب واحدة ضمن حملة — على طابور 'whatsapp' المعزول (لا يثقل media).
 * idempotent: لا يُعيد إرسال رسالة أُرسلت (حارس الحالة). يحدّث سجلّ الرسالة (نجاح + معرّف
 * المزوّد، أو فشل + السبب) ويزيد عدّادات الحملة ذرّياً؛ آخر رسالة تُنهي الحملة.
 */
class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public readonly int $messageId)
    {
        $this->onQueue('whatsapp');
    }

    public function handle(UltraMsgClient $client): void
    {
        $message = WhatsappCampaignMessage::query()->find($this->messageId);
        if ($message === null || $message->status !== WhatsappMessageStatus::Pending) {
            return; // محذوف أو أُرسل سابقاً — لا تكرار
        }

        $campaign = $message->campaign;
        if ($campaign === null || $campaign->status !== WhatsappCampaignStatus::Sending) {
            return; // أُلغيت الحملة أو انتهت — لا إرسال
        }

        $text = WhatsappCampaignContent::text($campaign);
        $mediaType = WhatsappCampaignContent::mediaType($campaign);
        $mediaUrl = WhatsappCampaignContent::mediaUrl($campaign);

        $result = match (true) {
            $mediaType === WhatsappMediaType::Image && $mediaUrl !== null => $client->sendImage($message->phone, $mediaUrl, $text),
            $mediaType === WhatsappMediaType::Video && $mediaUrl !== null => $client->sendVideo($message->phone, $mediaUrl, $text),
            default => $client->sendText($message->phone, $text),
        };

        if ($result->ok) {
            $message->status = WhatsappMessageStatus::Sent;
            $message->provider_message_id = $result->providerMessageId;
            $message->error = null;
            $message->sent_at = now();
        } else {
            $message->status = WhatsappMessageStatus::Failed;
            $message->error = $result->error;
        }
        $message->save();

        $this->bumpCounters($campaign->id, $result->ok);
    }

    /** فشل نهائيّ بعد استنفاد المحاولات — سجّل الرسالة فاشلة وحدّث العدّادات (لا تُعلَّق الحملة). */
    public function failed(\Throwable $e): void
    {
        $message = WhatsappCampaignMessage::query()->find($this->messageId);
        if ($message === null || $message->status !== WhatsappMessageStatus::Pending) {
            return;
        }
        $message->status = WhatsappMessageStatus::Failed;
        $message->error = $e->getMessage();
        $message->save();

        if ($message->whatsapp_campaign_id !== null) {
            $this->bumpCounters($message->whatsapp_campaign_id, false);
        }
    }

    /** زيادة عدّاد النجاح/الفشل ذرّياً + إنهاء الحملة عند اكتمال كل المستلمين. */
    private function bumpCounters(int $campaignId, bool $ok): void
    {
        DB::transaction(function () use ($campaignId, $ok): void {
            /** @var WhatsappCampaign|null $campaign */
            $campaign = WhatsappCampaign::query()->lockForUpdate()->find($campaignId);
            if ($campaign === null) {
                return;
            }

            if ($ok) {
                $campaign->sent_count++;
            } else {
                $campaign->failed_count++;
            }

            // اكتمل كل المستلمين ⇒ إنهاء: completed إن نجحت ولو رسالة، وإلا failed.
            if ($campaign->status === WhatsappCampaignStatus::Sending
                && ($campaign->sent_count + $campaign->failed_count) >= $campaign->recipients_total) {
                $campaign->status = $campaign->sent_count > 0
                    ? WhatsappCampaignStatus::Completed
                    : WhatsappCampaignStatus::Failed;
                $campaign->finished_at = now();
            }

            $campaign->save();
        });
    }
}
