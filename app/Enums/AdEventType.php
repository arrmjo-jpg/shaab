<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع حدث الإعلان المُتتبَّع. مستقلّ عن EngagementType (إعجاب/مشاهدة محتوى) — قياس
 * الإعلان مفصول عن تفاعل المحتوى. قابل للتوسعة (تفاعل/تحويل مستقبليّ).
 */
enum AdEventType: string
{
    case Impression = 'impression';
    case Click = 'click';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
