<?php

declare(strict_types=1);

namespace App\Enums;

enum RecaptchaVersion: string
{
    case V2 = 'v2';
    case V3 = 'v3';

    public function label(): string
    {
        return match ($this) {
            self::V2 => 'reCAPTCHA v2',
            self::V3 => 'reCAPTCHA v3',
        };
    }
}
