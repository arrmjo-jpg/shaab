<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Models\WhatsappCampaign;
use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\WhatsappCampaignContent;
use App\Support\Whatsapp\WhatsappRecipients;
use Illuminate\Http\JsonResponse;

/**
 * معاينة الرسالة قبل الإرسال — النصّ النهائيّ المُركَّب فعلاً (نفس مصدر الإرسال، فلا تباين)
 * + نوع/رابط الوسيط + عدد المستلمين المتوقَّع.
 */
class PreviewWhatsappCampaignAction
{
    public function handle(WhatsappCampaign $campaign): JsonResponse
    {
        $campaign->loadMissing(['groups:id', 'article.mediaAssets', 'mediaAsset']);
        $groupIds = $campaign->groups->pluck('id')->all();

        return ApiResponse::success(data: [
            'text' => WhatsappCampaignContent::text($campaign),
            'media_type' => WhatsappCampaignContent::mediaType($campaign)->value,
            'media_url' => WhatsappCampaignContent::mediaUrl($campaign),
            'recipients' => WhatsappRecipients::count($groupIds),
        ]);
    }
}
