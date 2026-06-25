<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Http\Resources\Admin\Whatsapp\WhatsappCampaignResource;
use App\Models\WhatsappCampaign;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ShowWhatsappCampaignAction
{
    public function handle(WhatsappCampaign $campaign): JsonResponse
    {
        return ApiResponse::success(
            data: (new WhatsappCampaignResource($campaign->load('groups:id,name')))->resolve(),
        );
    }
}
