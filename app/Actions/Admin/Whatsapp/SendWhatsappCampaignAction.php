<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappCampaignStatus;
use App\Http\Resources\Admin\Whatsapp\WhatsappCampaignResource;
use App\Jobs\DispatchWhatsappCampaignJob;
use App\Models\WhatsappCampaign;
use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\UltraMsgClient;
use Illuminate\Http\JsonResponse;

/**
 * إرسال فوريّ للحملة — يحوّلها إلى sending ويُسلِّم التنسيق لـ DispatchWhatsappCampaignJob
 * (لا إرسال مباشر في الطلب — كله عبر الطابور). يُسمح فقط من draft/scheduled.
 */
class SendWhatsappCampaignAction
{
    public function handle(WhatsappCampaign $campaign): JsonResponse
    {
        if (! in_array($campaign->status, [WhatsappCampaignStatus::Draft, WhatsappCampaignStatus::Scheduled], true)) {
            return ApiResponse::error(__('whatsapp.campaign.not_sendable'), [], 422);
        }

        if (! (new UltraMsgClient)->isConfigured()) {
            return ApiResponse::error(__('whatsapp.campaign.not_configured'), [], 422);
        }

        $campaign->status = WhatsappCampaignStatus::Sending;
        $campaign->started_at = now();
        $campaign->finished_at = null;
        $campaign->sent_count = 0;
        $campaign->failed_count = 0;
        $campaign->save();

        DispatchWhatsappCampaignJob::dispatch($campaign->id)->afterCommit();

        return ApiResponse::success(
            __('whatsapp.campaign.sending'),
            new WhatsappCampaignResource($campaign->load('groups:id,name')),
        );
    }
}
