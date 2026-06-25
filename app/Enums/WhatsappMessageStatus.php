<?php

declare(strict_types=1);

namespace App\Enums;

/** حالة رسالة واحدة داخل حملة واتساب. */
enum WhatsappMessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
