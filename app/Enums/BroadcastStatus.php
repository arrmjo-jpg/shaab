<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * دورة حياة البثّ: مسودة → مجدول → مباشر → (غير متّصل/منتهٍ/فشل) → مؤرشف.
 * الانتقالات محكومة بالصلاحيات وآلة الحالة في الـ Action (المرحلة B2).
 */
enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Offline = 'offline';
    case Ended = 'ended';
    case Failed = 'failed';
    case Archived = 'archived';

    public function label(): string
    {
        return __('broadcast.status.'.$this->value);
    }

    /**
     * آلة الحالة — مصدر الحقيقة الوحيد للانتقالات المسموحة. مؤرشف حالة نهائية.
     * لا انتقالات عكسية غير آمنة (ended/archived/failed ⇏ live).
     *
     * @return array<int,self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Archived],
            self::Scheduled => [self::Live, self::Failed, self::Archived],
            self::Live => [self::Offline, self::Ended, self::Failed],
            self::Offline => [self::Live, self::Ended, self::Failed],
            self::Failed => [self::Archived],
            self::Ended => [self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
