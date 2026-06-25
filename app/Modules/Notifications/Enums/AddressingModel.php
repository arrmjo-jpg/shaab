<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * نموذج عنونة الدرايفر: per_recipient (email/whatsapp/sms — حلقة على المستلمين ⇒ deliveries)
 * أو topic (firebase topic/broadcast — نشرة واحدة ⇒ عدّادات فقط بلا deliveries). يحدّد TrackingMode.
 */
enum AddressingModel: string
{
    case PerRecipient = 'per_recipient';
    case Topic = 'topic';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $a): string => $a->value, self::cases());
    }
}
