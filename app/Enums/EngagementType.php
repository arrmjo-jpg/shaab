<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع التفاعل الموحّد (منصّي، عابر لأنواع المحتوى).
 *
 * - like / dislike : تفاعل أحادي حصري (واحد فقط لكل مستخدم/جهاز لكل هدف)
 * - favorite       : حفظ/إشارة مرجعية مستقلّة (تبديل)
 */
enum EngagementType: string
{
    case Like = 'like';
    case Dislike = 'dislike';
    case Favorite = 'favorite';

    /** تفاعلات حصرية متبادلة (like ⇔ dislike). */
    public function isReaction(): bool
    {
        return $this === self::Like || $this === self::Dislike;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
