<?php

declare(strict_types=1);

namespace App\Modules\CDN\Enums;

enum CdnPlan: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Business = 'business';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'مجاني',
            self::Pro => 'احترافي',
            self::Business => 'أعمال',
            self::Enterprise => 'مؤسسي',
        };
    }
}
