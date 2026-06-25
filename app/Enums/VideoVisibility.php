<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * رؤية الفيديو/القائمة:
 *   public   — مدرَج في القوائم والخرائط، متاح للجميع.
 *   unlisted — متاح بالرابط القانوني المباشر فقط، مستبعَد من القوائم/الخرائط.
 *   private  — غير متاح علناً (للإدارة فقط).
 */
enum VideoVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';

    public function label(): string
    {
        return __('video.visibility.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
