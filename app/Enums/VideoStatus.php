<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * دورة حياة الفيديو: مسودة → (إرسال للمراجعة) → قيد المراجعة → مجدول → منشور،
 * مع مرفوض ومؤرشف. الانتقالات محكومة بالصلاحيات في الـ Action/Guard.
 * حالات الكاتب (submitted/in_review/rejected) مطابقة لنموذج المقال/الريل.
 */
enum VideoStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return __('video.status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
