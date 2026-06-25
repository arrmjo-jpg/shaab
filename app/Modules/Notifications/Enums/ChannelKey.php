<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * قناة الإرسال — driver واحد لكلّ قيمة. v1.1: firebase|whatsapp|email فقط؛
 * sms|telegram|webpush تُضاف مع درايفراتها لاحقاً (v1.2) — لا cases ميتة بلا درايفر.
 */
enum ChannelKey: string
{
    case Firebase = 'firebase';
    case Whatsapp = 'whatsapp';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Firebase => 'Firebase Push',
            self::Whatsapp => 'WhatsApp',
            self::Email => 'البريد الإلكترونيّ',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
