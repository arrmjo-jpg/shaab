<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * مزوّدو التضمين المسموح بهم (allow-list). التضمينات ليست ملفات —
 * تُمثَّل ككتل محتوى مُعقَّمة داخل محرّر TipTap، لا جدول لها.
 */
enum EmbedProvider: string
{
    case YouTube = 'youtube';
    case Vimeo = 'vimeo';
    case Twitter = 'twitter';
    case Facebook = 'facebook';
    case Instagram = 'instagram';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $p): string => $p->value, self::cases());
    }
}
