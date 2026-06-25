<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/** أولويّة الإشعار — تؤثّر على الطابور وQuiet Hours وتجاوز Kill Switch وسياسة إعادة المحاولة. */
enum Priority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'حرجة',
            self::High => 'عالية',
            self::Normal => 'عاديّة',
            self::Low => 'منخفضة',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $p): string => $p->value, self::cases());
    }
}
