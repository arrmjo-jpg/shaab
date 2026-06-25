<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * وضع تنسيب تصنيف ووردبريس المختار. تصنيف تحريري صريح بقرار المُشغِّل —
 * لا تصنيف تكهّني ولا هجين. كل تصنيف مصدر يُستثنى أو يُسنَد لنوع واحد فقط:
 *
 * - exclude  : لا يُرحَّل (دلو عرض/نظامي أو غير مختار).
 * - news     : منشوراته → ArticleType::News، وهدفه تصنيف يقبل الأخبار (scope news/both).
 * - articles : منشوراته → ArticleType::Opinion، وهدفه تصنيف يقبل المقالات (scope opinion/both).
 *
 * (مجمّعات الأخبار/المقالات يميّزها عمود categories.scope، ويُفرَض التوافق في ArticleCategoryGuard.)
 */
enum WpCategoryMode: string
{
    case Exclude = 'exclude';
    case News = 'news';
    case Articles = 'articles';

    public function label(): string
    {
        return __('wp_migration.category_mode.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    /** هل التصنيف مختار للترحيل (يتطلّب هدفاً واحداً). */
    public function isIncluded(): bool
    {
        return $this !== self::Exclude;
    }

    /** نوع المقال الناتج لهذا الوضع (news → News، articles → Opinion). */
    public function articleType(): ?ArticleType
    {
        return match ($this) {
            self::News => ArticleType::News,
            self::Articles => ArticleType::Opinion,
            self::Exclude => null,
        };
    }
}
