<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * تصرّف المُشغِّل في تصنيف مصدر ووردبريس — محور مستقلّ عن نوع المحتوى (WpCategoryMode).
 * قرار صريح لكل تصنيف، بلا تكهّن:
 *
 * - create  : إنشاء تصنيف AlphaCMS جديد من تصنيف المصدر (مع حفظ الهرمية).
 * - map     : ربط بتصنيف AlphaCMS قائم (target_category_id مطلوب).
 * - exclude : تجاهل تماماً (لا إنشاء ولا ربط).
 *
 * النوع (news/articles) يبقى مطلوباً لكل مُضمَّن (create|map) عبر WpCategoryMode.
 */
enum WpCategoryDisposition: string
{
    case Create = 'create';
    case Map = 'map';
    case Exclude = 'exclude';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    /** هل التصنيف مُضمَّن في الترحيل (يتطلّب نوع محتوى وهدفاً محسوماً). */
    public function isIncluded(): bool
    {
        return $this !== self::Exclude;
    }
}
