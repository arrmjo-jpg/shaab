<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * استراتيجية اختيار الإعلان لكل مساحة (server-side). قابلة للتوسعة:
 *   - weighted    : اختيار موزون بوزن صريح (creative/placement) — ليس بالنقرات.
 *   - round_robin : تناوب تسلسليّ عادل عبر المرشّحين.
 *   - even        : توزيع متساوٍ (تجاهل الأوزان).
 *
 * مُحسِّن CTR/Thompson مستقبليّ يُضاف كقيمة جديدة دون كسر العقد (optimization-ready).
 */
enum AdSelectorStrategy: string
{
    case Weighted = 'weighted';
    case RoundRobin = 'round_robin';
    case Even = 'even';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
