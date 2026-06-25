<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة استخراج نصّ العدد (OCR pipeline). pending: مُجدوَل؛ processing: قيد التنفيذ؛
 * done: اكتمل (نصّ كامل أو غيابه مؤكَّد)؛ partial: بعض الصفحات فقط بها نصّ؛
 * failed: تعذّر الاستخراج (لا أداة/لا وثيقة محلّية/خطأ).
 */
enum EpaperOcrStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Partial = 'partial';
    case Failed = 'failed';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
