<?php

declare(strict_types=1);

namespace App\Enums;

/** حالة مرحلة ترحيل Vertix. */
enum VertixRunStatus: string
{
    case Idle = 'idle';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
