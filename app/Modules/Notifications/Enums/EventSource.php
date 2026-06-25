<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * مصدر الحدث — المصادر الأربعة متكافئة معماريًّا (لا مصدر أساسيّ): نشر محتوى،
 * مجدول، يدويّ (أدمن)، نظاميّ (صحّة/طوابير). تمرّ جميعها بخطّ ابتلاع واحد.
 */
enum EventSource: string
{
    case Domain = 'domain';
    case Scheduled = 'scheduled';
    case Manual = 'manual';
    case System = 'system';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s): string => $s->value, self::cases());
    }
}
