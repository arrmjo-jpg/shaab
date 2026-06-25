<?php

declare(strict_types=1);

namespace App\Enums;

/** نوع حملة واتساب: رسالة إعلانية حرّة أو خبر (يُجلب محتواه تلقائياً من المقال). */
enum WhatsappCampaignType: string
{
    case Promo = 'promo';
    case Article = 'article';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
