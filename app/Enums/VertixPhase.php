<?php

declare(strict_types=1);

namespace App\Enums;

/** مرحلة ترحيل Vertix — مرحلتان مستقلّتان متتابعتان. */
enum VertixPhase: string
{
    case Categories = 'categories';
    case News = 'news';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
