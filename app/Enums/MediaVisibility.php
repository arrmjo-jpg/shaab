<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    public function disk(): string
    {
        return $this === self::Public ? 'public' : 'local';
    }
}
