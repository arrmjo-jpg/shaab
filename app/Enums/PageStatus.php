<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * دورة حياة الصفحة الثابتة: مسودة → منشور → مؤرشف.
 * الانتقالات يدوية محكومة بالصلاحيات في TransitionPageStatusAction.
 * (لا «مجدول» — الصفحات الثابتة تُنشر مباشرةً، لا جدولة زمنية.)
 */
enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return __('page.status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
