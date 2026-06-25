<?php

declare(strict_types=1);

namespace App\Enums;

enum CategoryStatus: string
{
    case Active = 'active';
    case Hidden = 'hidden';

    public function label(): string
    {
        return __('category.status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
