<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * متى تظهر نتائج الاستطلاع. مُخزَّنة وقابلة للتحرير في الإدارة (Phase 1)؛ الفرض على
 * السطح العام يأتي في Phase 2 (عرض النتائج العام).
 *
 *   - always      : النتائج ظاهرة دائماً.
 *   - after_vote  : تظهر بعد إدلاء الناخب بصوته.
 *   - after_close : تظهر بعد انتهاء/إغلاق الاستطلاع.
 */
enum PollResultVisibility: string
{
    case Always = 'always';
    case AfterVote = 'after_vote';
    case AfterClose = 'after_close';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
