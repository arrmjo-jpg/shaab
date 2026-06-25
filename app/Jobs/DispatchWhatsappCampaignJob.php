<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WhatsappCampaignStatus;
use App\Enums\WhatsappMessageStatus;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignMessage;
use App\Settings\ThirdPartySettings;
use App\Support\Whatsapp\WhatsappRecipients;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * منسّق إرسال حملة واتساب — على طابور 'whatsapp'. يبني صفوف الرسائل (لقطة رقم لكل مستلم،
 * idempotent عبر unique(campaign,phone)) ثم يطلق SendWhatsappMessageJob لكل مستلم بتأخير
 * تراكميّ مشتقّ من إعدادات whatsapp_batch_size/whatsapp_delay_seconds (throttling احترام
 * حدود المزوّد). صفر مستلمين ⇒ إنهاء فوريّ (failed). Transactions عند بناء الصفوف.
 */
class DispatchWhatsappCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue('whatsapp');
    }

    public function handle(): void
    {
        /** @var WhatsappCampaign|null $campaign */
        $campaign = WhatsappCampaign::query()->with('groups:id')->find($this->campaignId);
        if ($campaign === null || $campaign->status !== WhatsappCampaignStatus::Sending) {
            return; // أُلغيت أو ليست في حالة الإرسال
        }

        $groupIds = $campaign->groups->pluck('id')->all();
        $settings = app(ThirdPartySettings::class);
        $batchSize = max(1, $settings->whatsapp_batch_size);
        $delaySeconds = max(0, $settings->whatsapp_delay_seconds);

        $total = 0;
        $index = 0;

        // قراءة تدفّقية للمستلمين (قد يكونون آلافاً) — صفّ لكل مستلم ثم Job مؤجَّل.
        WhatsappRecipients::query($groupIds)
            ->select(['id', 'phone'])
            ->chunkById(500, function ($contacts) use ($campaign, &$total, &$index, $batchSize, $delaySeconds): void {
                foreach ($contacts as $contact) {
                    $message = $this->createMessageRow($campaign->id, (int) $contact->id, (string) $contact->phone);
                    if ($message === null) {
                        continue; // مكرّر (unique) — تخطٍّ آمن
                    }
                    $total++;

                    // تأخير تراكميّ: كل دفعة (batchSize) تبدأ بعد delaySeconds إضافية.
                    $delay = intdiv($index, $batchSize) * $delaySeconds;
                    SendWhatsappMessageJob::dispatch($message->id)->delay(now()->addSeconds($delay));
                    $index++;
                }
            });

        // ثبّت عدد المستلمين الفعليّ؛ صفر ⇒ إنهاء فوريّ (لا أحد لإرساله).
        $campaign->recipients_total = $total;
        if ($total === 0) {
            $campaign->status = WhatsappCampaignStatus::Failed;
            $campaign->finished_at = now();
        }
        $campaign->save();
    }

    /** صفّ رسالة (pending) — يتخطّى التكرار عبر unique(campaign,phone). */
    private function createMessageRow(int $campaignId, int $contactId, string $phone): ?WhatsappCampaignMessage
    {
        try {
            return DB::transaction(fn (): WhatsappCampaignMessage => WhatsappCampaignMessage::create([
                'whatsapp_campaign_id' => $campaignId,
                'whatsapp_contact_id' => $contactId,
                'phone' => $phone,
                'status' => WhatsappMessageStatus::Pending->value,
            ]));
        } catch (\Throwable) {
            return null; // انتهاك unique(campaign,phone) — مستلم مكرّر
        }
    }
}
