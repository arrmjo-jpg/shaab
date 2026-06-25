<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة دورة حياة المقال (ADR §7).
 * انتقالات الحالة محكومة بالأدوار وتُنفَّذ في موجة «سير عمل النشر»
 * اللاحقة — هذه الموجة (C2) تُسلّم التعداد والعمود فقط (الإنشاء = draft).
 */
enum ArticleStatus: string
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
        return __('article.status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
