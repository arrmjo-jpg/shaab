<?php

declare(strict_types=1);

namespace App\Enums;

enum WatermarkPosition: string
{
    case TopLeft = 'top-left';
    case TopRight = 'top-right';
    case BottomLeft = 'bottom-left';
    case BottomRight = 'bottom-right';
    case Center = 'center';

    public function label(): string
    {
        return match ($this) {
            self::TopLeft => 'أعلى اليسار',
            self::TopRight => 'أعلى اليمين',
            self::BottomLeft => 'أسفل اليسار',
            self::BottomRight => 'أسفل اليمين',
            self::Center => 'الوسط',
        };
    }
}
