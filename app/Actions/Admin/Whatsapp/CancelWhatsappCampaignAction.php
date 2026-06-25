<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappCampaignStatus;
use App\Http\Resources\Admin\Whatsapp\WhatsappCampaignResource;
use App\Models\WhatsappCampaign;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/** إلغاء حملة — مسموح من draft/scheduled فقط (الجارية sending لها رسائل مُطلَقة في الطابور). */
class CancelWhatsappCampaignAction
{
    public function handle(WhatsappCampaign $campaign): JsonResponse
    {
        if (! in_array($campaign->status, [WhatsappCampaignStatus::Draft, WhatsappCampaignStatus::Scheduled], true)) {
            return ApiResponse::error(__('whatsapp.campaign.not_cancellable'), [], 422);
        }

        $campaign->status = WhatsappCampaignStatus::Cancelled;
        $campaign->save();

        return ApiResponse::success(
            __('whatsapp.campaign.cancelled'),
            new WhatsappCampaignResource($campaign->load('groups:id,name')),
        );
    }
}
