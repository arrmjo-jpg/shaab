<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * سياسة حسم تعارض النوع لمنشور ينتمي لتصنيفات مختارة بأنواع متضاربة (أخبار/مقالات).
 * يختارها المُشغِّل صراحةً في معاينة الأثر قبل التنفيذ — لا تخطّي ضمنيّ إطلاقاً.
 *
 * - prefer_news     : المنشور المتعارض → خبر (هدف تصنيف الأخبار المُنسَّب).
 * - prefer_articles : المنشور المتعارض → مقال رأي (هدف تصنيف المقالات المُنسَّب).
 * - exclude         : المنشور المتعارض يُستثنى صراحةً (skipped + flag).
 */
enum ConflictPolicy: string
{
    case PreferNews = 'prefer_news';
    case PreferArticles = 'prefer_articles';
    case Exclude = 'exclude';

    public function label(): string
    {
        return __('wp_migration.conflict_policy.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    /** نوع المقال الناتج للمنشور المتعارض، أو null إذا كانت السياسة استثناء. */
    public function resolvedType(): ?ArticleType
    {
        return match ($this) {
            self::PreferNews => ArticleType::News,
            self::PreferArticles => ArticleType::Opinion,
            self::Exclude => null,
        };
    }
}
