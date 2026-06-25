<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * فئة الجهاز — بُعد أهليّة وعرض. تُجزّئ بِركة المرشّحين (الأهليّة قد تختلف حسب الجهاز)
 * وتدخل بذرة الاختيار الحتميّ. تُحلّ من الطلب (UA/Client-Hints) في موجة الواجهة العامة.
 */
enum AdDeviceClass: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    public static function default(): self
    {
        return self::Desktop;
    }

    /** تحليل متساهل: قيمة غير معروفة/فارغة ⇒ الافتراضي (لا فشل خدمة). */
    public static function fromString(?string $value): self
    {
        return $value !== null && $value !== ''
            ? (self::tryFrom($value) ?? self::default())
            : self::default();
    }
}
