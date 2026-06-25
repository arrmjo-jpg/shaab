<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * دورة حياة العدد الرقميّ:
 *  draft → scheduled (نشر مُجدوَل) → published → archived.
 * soft delete مستقلّ عن الحالة (استرجاع ممكن).
 */
enum EpaperStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return __('epaper.status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
