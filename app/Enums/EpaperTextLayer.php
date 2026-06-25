<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * طبقة النصّ في وثيقة العدد. present: كل الصفحات بها نصّ مُستخرَج؛ partial: بعضها؛
 * absent: لا نصّ (وثيقة ممسوحة ضوئياً بلا OCR، أو لم يُفعَّل مزوّد OCR).
 */
enum EpaperTextLayer: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Partial = 'partial';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
