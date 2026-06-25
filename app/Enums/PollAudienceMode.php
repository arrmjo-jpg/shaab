<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * جمهور التصويت في الاستطلاع. مُخزَّن وقابل للتحرير في الإدارة (Phase 1)؛ الفرض وقت
 * التشغيل (رفض تصويت الزائر على استطلاع authenticated) يأتي في Phase 2 (التصويت العام).
 *
 *   - public        : زوّار + مستخدمون مُصادَقون.
 *   - authenticated : مستخدمون مُصادَقون فقط.
 */
enum PollAudienceMode: string
{
    case Everyone = 'public';
    case Authenticated = 'authenticated';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
