<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappMediaType;
use App\Models\WhatsappCampaign;
use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\PhoneNumber;
use App\Support\Whatsapp\UltraMsgClient;
use App\Support\Whatsapp\WhatsappCampaignContent;
use Illuminate\Http\JsonResponse;

/**
 * إرسال اختبار لرقم واحد — يُرسَل تزامنياً (تغذية راجعة فورية للمدير) بنفس محتوى الحملة.
 * لا يلمس سجلّ الحملة ولا عدّاداتها. الرقم يُطبَّع E.164 ويُرفَض المحلّي/غير الصالح.
 */
class TestWhatsappCampaignAction
{
    /** @param  array<string,mixed>  $validated */
    public function handle(WhatsappCampaign $campaign, array $validated): JsonResponse
    {
        $phone = PhoneNumber::normalize((string) $validated['phone']);
        if ($phone === null) {
            return ApiResponse::error(__('whatsapp.contact.invalid_phone'), [], 422);
        }

        $client = new UltraMsgClient;
        if (! $client->isConfigured()) {
            return ApiResponse::error(__('whatsapp.campaign.not_configured'), [], 422);
        }

        $campaign->loadMissing(['article.mediaAssets', 'mediaAsset']);
        $text = WhatsappCampaignContent::text($campaign);
        $mediaType = WhatsappCampaignContent::mediaType($campaign);
        $mediaUrl = WhatsappCampaignContent::mediaUrl($campaign);

        $result = match (true) {
            $mediaType === WhatsappMediaType::Image && $mediaUrl !== null => $client->sendImage($phone, $mediaUrl, $text),
            $mediaType === WhatsappMediaType::Video && $mediaUrl !== null => $client->sendVideo($phone, $mediaUrl, $text),
            default => $client->sendText($phone, $text),
        };

        if ($result->ok) {
            return ApiResponse::success(__('whatsapp.campaign.test_sent'));
        }

        return ApiResponse::error(__('whatsapp.campaign.test_failed'), ['reason' => $result->error], 422);
    }
}
