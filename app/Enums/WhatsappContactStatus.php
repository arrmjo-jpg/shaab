<?php

declare(strict_types=1);

namespace App\Enums;

/** حالة اشتراك جهة اتصال واتساب. */
enum WhatsappContactStatus: string
{
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
