<?php

declare(strict_types=1);

namespace App\Enums;

/** وسيط الرسالة الإعلانية: بلا وسيط (نص) أو صورة أو فيديو (مع نص اختياري كـ caption). */
enum WhatsappMediaType: string
{
    case None = 'none';
    case Image = 'image';
    case Video = 'video';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
