<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * حالة تسليم لكلّ مُستلِم (قنوات per_recipient فقط — لا تُنشأ لقنوات topic).
 * invalid = عنوان/توكن ميت يُقلَّم (لا يُحسَب فشلاً للحملة).
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Invalid = 'invalid';
    case Skipped = 'skipped';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s): string => $s->value, self::cases());
    }
}
