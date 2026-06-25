<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/** منصّة جهاز الـpush (مصدر استهداف Android/iOS وعنونة الضيوف). */
enum DevicePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';
    case Web = 'web';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $p): string => $p->value, self::cases());
    }
}
