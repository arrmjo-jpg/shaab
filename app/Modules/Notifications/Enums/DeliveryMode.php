<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * سلوك (event × channel): تلقائيّ (يُرسَل عند الحدث) | موافقة يدويّة (مسوّدة تنتظر
 * اعتماد الأدمن قبل الإرسال) | معطّل.
 */
enum DeliveryMode: string
{
    case Automatic = 'automatic';
    case ManualApproval = 'manual_approval';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'تلقائيّ',
            self::ManualApproval => 'موافقة يدويّة',
            self::Disabled => 'معطّل',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $m): string => $m->value, self::cases());
    }
}
