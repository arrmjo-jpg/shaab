<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappCampaignStatus;
use App\Enums\WhatsappCampaignType;
use App\Enums\WhatsappMediaType;
use App\Http\Resources\Admin\Whatsapp\WhatsappCampaignResource;
use App\Models\WhatsappCampaign;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء حملة (مسوّدة أو مجدوَلة) — promo أو article. منع التكرار بالخطأ عبر dedupe_hash
 * (محتوى + مجموعات متطابقة أُنشئت خلال نافذة قصيرة وما زالت حيّة). Transaction عند الحفظ.
 */
class CreateWhatsappCampaignAction
{
    private const DEDUPE_WINDOW_MINUTES = 5;

    /** @param  array<string,mixed>  $validated */
    public function handle(array $validated, int $userId): JsonResponse
    {
        $type = WhatsappCampaignType::from($validated['type']);
        $groupIds = array_values(array_unique(array_map('intval', $validated['groups'])));
        sort($groupIds);

        $isArticle = $type === WhatsappCampaignType::Article;
        $mediaType = $isArticle
            ? WhatsappMediaType::None->value
            : (string) ($validated['media_type'] ?? WhatsappMediaType::None->value);
        $mediaAssetId = $isArticle ? null : ($validated['media_asset_id'] ?? null);
        $articleId = $isArticle ? (int) $validated['article_id'] : null;
        $messageText = $isArticle ? null : ($validated['message_text'] ?? null);

        $hash = hash('sha256', (string) json_encode([
            $type->value, $messageText, $mediaType, $mediaAssetId, $articleId, $groupIds,
        ]));

        $duplicate = WhatsappCampaign::query()
            ->where('dedupe_hash', $hash)
            ->whereIn('status', [
                WhatsappCampaignStatus::Draft->value,
                WhatsappCampaignStatus::Scheduled->value,
                WhatsappCampaignStatus::Sending->value,
            ])
            ->where('created_at', '>=', now()->subMinutes(self::DEDUPE_WINDOW_MINUTES))
            ->exists();
        if ($duplicate) {
            return ApiResponse::error(__('whatsapp.campaign.duplicate'), [], 422);
        }

        $scheduledAt = ! empty($validated['scheduled_at']) ? $validated['scheduled_at'] : null;
        $status = $scheduledAt !== null
            ? WhatsappCampaignStatus::Scheduled->value
            : WhatsappCampaignStatus::Draft->value;

        $campaign = DB::transaction(function () use ($validated, $type, $status, $messageText, $mediaType, $mediaAssetId, $articleId, $scheduledAt, $hash, $userId, $groupIds): WhatsappCampaign {
            $campaign = WhatsappCampaign::create([
                'name' => $validated['name'],
                'type' => $type->value,
                'status' => $status,
                'message_text' => $messageText,
                'media_type' => $mediaType,
                'media_asset_id' => $mediaAssetId,
                'article_id' => $articleId,
                'scheduled_at' => $scheduledAt,
                'dedupe_hash' => $hash,
                'created_by' => $userId,
            ]);
            $campaign->groups()->sync($groupIds);

            return $campaign;
        });

        return ApiResponse::success(
            __('whatsapp.campaign.created'),
            new WhatsappCampaignResource($campaign->load('groups:id,name')),
            201,
        );
    }
}
