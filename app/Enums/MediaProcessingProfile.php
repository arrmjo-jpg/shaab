<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * تلميح معالجة وسائط محايد للمحتوى — يضبط مخرجات خط الترميز القائم دون
 * إنشاء خط موازٍ. القيمة null (الافتراضية) = السلوك الحالي (HLS + poster).
 *
 * Reel: يضيف نسخ MP4 تدريجية + صورة مصغّرة WebP (لفيديوهات الريلز العمودية).
 */
enum MediaProcessingProfile: string
{
    case Reel = 'reel';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
