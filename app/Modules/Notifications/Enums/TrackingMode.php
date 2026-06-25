<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * نمط تتبّع القناة داخل الحملة: aggregate (عدّادات فقط — قنوات topic، بلا صفوف deliveries)
 * أو per_recipient (صفّ deliveries لكلّ مُستلِم — email/whatsapp/sms). مشتقّ من AddressingModel.
 */
enum TrackingMode: string
{
    case Aggregate = 'aggregate';
    case PerRecipient = 'per_recipient';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $t): string => $t->value, self::cases());
    }
}
