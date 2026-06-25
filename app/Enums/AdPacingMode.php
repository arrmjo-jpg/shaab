<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نمط وتيرة الصرف للحملة (pacing) — جاهز-مستقبلاً. لا محرّك وتيرة في هذه المرحلة؛
 * العمود مُخزَّن لتمكين البناء لاحقاً دون هجرة جديدة.
 *
 *   - none : بلا وتيرة (يُخدَم متى كان نشطاً وضمن النافذة).
 *   - even : توزيع متساوٍ عبر مدّة الحملة (مستقبليّ).
 *   - asap : أسرع صرف ممكن (مستقبليّ).
 */
enum AdPacingMode: string
{
    case None = 'none';
    case Even = 'even';
    case Asap = 'asap';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
