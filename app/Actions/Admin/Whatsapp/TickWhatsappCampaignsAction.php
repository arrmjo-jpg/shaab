<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappCampaignStatus;
use App\Jobs\DispatchWhatsappCampaignJob;
use App\Models\WhatsappCampaign;
use Carbon\CarbonInterface;

/**
 * يطلق الحملات المجدوَلة المستحقّة (scheduled و scheduled_at <= now) — يحوّلها sending
 * ويُسلِّمها لـ DispatchWhatsappCampaignJob. مُدار عبر SchedulerRegistry (everyMinute).
 * idempotent: لا يلمس إلا المستحقّ.
 */
class TickWhatsappCampaignsAction
{
    public function handle(?CarbonInterface $now = null): int
    {
        $now ??= now();

        $due = WhatsappCampaign::query()
            ->where('status', WhatsappCampaignStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        $started = 0;
        foreach ($due as $campaign) {
            $campaign->status = WhatsappCampaignStatus::Sending;
            $campaign->started_at = $now;
            $campaign->finished_at = null;
            $campaign->sent_count = 0;
            $campaign->failed_count = 0;
            $campaign->save();

            DispatchWhatsappCampaignJob::dispatch($campaign->id)->afterCommit();
            $started++;
        }

        return $started;
    }
}
