<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/** قرار التوجيه الافتراضيّ للحدث في الكتالوج: حملة جماعيّة أو إشعار مباشر per-user. */
enum DispatchMode: string
{
    case Campaign = 'campaign';
    case Direct = 'direct';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $d): string => $d->value, self::cases());
    }
}
