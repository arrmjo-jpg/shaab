<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/** نطاق تفضيل المستخدم (إلغاء الاشتراك): عامّ كلّيّ | تصنيف | حدث بعينه | topic بعينه (كتم اشتراك push). */
enum PreferenceScope: string
{
    case Global = 'global';
    case Category = 'category';
    case Event = 'event';
    case Topic = 'topic';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s): string => $s->value, self::cases());
    }
}
