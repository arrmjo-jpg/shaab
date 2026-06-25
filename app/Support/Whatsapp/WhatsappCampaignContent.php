<?php

declare(strict_types=1);

namespace App\Support\Whatsapp;

use App\Enums\WhatsappCampaignType;
use App\Enums\WhatsappMediaType;
use App\Models\MediaAsset;
use App\Models\WhatsappCampaign;

/**
 * المحتوى النهائيّ لرسالة الحملة — مصدر واحد للمعاينة والإرسال (تطابق ما يُعرَض مع ما يُرسَل):
 *   • إعلانية: نص المركِّب (اسم الموقع + النص + رابط الموقع) + وسيط الحملة (صورة/فيديو) إن وُجد.
 *   • خبر: نص المقال (العنوان + الملخص + الرابط) + غلاف المقال كصورة (إن وُجد).
 * بلا أي حقول خارج المتفق عليه.
 */
final class WhatsappCampaignContent
{
    /** نص الرسالة (body للنص، caption للوسيط). */
    public static function text(WhatsappCampaign $campaign): string
    {
        if ($campaign->type === WhatsappCampaignType::Article && $campaign->article !== null) {
            return WhatsappMessageComposer::article($campaign->article);
        }

        return WhatsappMessageComposer::promo($campaign->message_text);
    }

    public static function mediaType(WhatsappCampaign $campaign): WhatsappMediaType
    {
        if ($campaign->type === WhatsappCampaignType::Article) {
            // الخبر: صورة الغلاف (إن وُجدت) — وإلا نصّ فقط.
            return self::articleCoverUrl($campaign) !== null ? WhatsappMediaType::Image : WhatsappMediaType::None;
        }

        return $campaign->media_type;
    }

    public static function mediaUrl(WhatsappCampaign $campaign): ?string
    {
        if ($campaign->type === WhatsappCampaignType::Article) {
            return self::articleCoverUrl($campaign);
        }

        return $campaign->mediaAsset?->url();
    }

    /** رابط غلاف المقال العام (collection=cover) أو null. */
    private static function articleCoverUrl(WhatsappCampaign $campaign): ?string
    {
        $article = $campaign->article;
        if ($article === null) {
            return null;
        }

        $cover = $article->mediaAssets
            ->first(fn (MediaAsset $a): bool => $a->pivot->collection === 'cover');

        return $cover?->url();
    }
}
