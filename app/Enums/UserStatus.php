<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';

    public function label(): string
    {
        return __('user.status.'.$this->value);
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
