<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/** كيف أُنشئت الحملة: تلقائيًّا بحدث | يدويًّا (أدمن) | عبر المجدول. */
enum TriggerType: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    case Scheduled = 'scheduled';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $t): string => $t->value, self::cases());
    }
}
