<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappCampaignStatus;
use App\Models\WhatsappCampaign;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/** حذف حملة (ناعم) — يُمنع أثناء الإرسال (sending) لتفادي تعليق رسائل في الطابور. */
class DeleteWhatsappCampaignAction
{
    public function handle(WhatsappCampaign $campaign): JsonResponse
    {
        if ($campaign->status === WhatsappCampaignStatus::Sending) {
            return ApiResponse::error(__('whatsapp.campaign.sending_locked'), [], 422);
        }

        $campaign->delete();

        return ApiResponse::success(__('whatsapp.campaign.deleted'));
    }
}
