<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نطاق صلاحية التصنيف لأنواع المحتوى (قرار معماري مقفول).
 *
 * - news    : صالح للأخبار/التغطية الحيّة فقط
 * - opinion : صالح للرأي فقط
 * - both    : صالح للجميع (افتراضي)
 *
 * توافق النوع↔النطاق يُفرَض في ArticleCategoryGuard.
 */
enum CategoryScope: string
{
    case News = 'news';
    case Opinion = 'opinion';
    case Both = 'both';

    public function label(): string
    {
        return __('category.scope.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    /** هل يسمح هذا النطاق بنوع مقال معيّن (news/live ⇒ news، opinion ⇒ opinion). */
    public function allowsArticleScope(self $articleScope): bool
    {
        return $this === self::Both || $this === $articleScope;
    }
}
