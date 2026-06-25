<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة الحملة الإعلانية. الانتقالات المسموحة تُفرَض في موجة دورة الحياة (Batch 4)،
 * لا هنا — هذا التعداد مصدر القيم فقط.
 *
 *   draft → scheduled → active ⇄ paused → completed
 *   (any) → archived
 */
enum AdCampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Archived = 'archived';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    /**
     * الحالات المؤهَّلة للعرض متى توفّرت النافذة الزمنية — **مصدر واحد** لمنطق الأهليّة.
     * التواريخ مصدر الحقيقة؛ draft/paused/completed/archived محجوبة إداريًّا (لا تُخدَم).
     *
     * @return array<int,self>
     */
    public static function servable(): array
    {
        return [self::Scheduled, self::Active];
    }

    /** @return array<int,string> قيم الحالات المؤهَّلة — لاستعلامات whereIn. */
    public static function servableValues(): array
    {
        return array_map(fn (self $s): string => $s->value, self::servable());
    }

    /** هل هذه الحالة مؤهَّلة للعرض متى توفّرت النافذة الزمنية؟ (scheduled أو active). */
    public function isServable(): bool
    {
        return in_array($this, self::servable(), true);
    }
}
