<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع المحتوى — مُقيَّد بنموذج العمل الحقيقي (قرار معماري مقفول).
 *
 * - news   : خبر صحفي قياسي (إداري/كاتب — محتوى مدفوع بالتصنيف)
 * - opinion: محتوى رأي يُنسب لكاتب (تصنيف واحد فقط)
 * - live   : تغطية حيّة مستمرّة (إداري فقط — خط زمني عام عبر ArticleUpdate)
 *
 * أنواع تكهّنية مرفوضة نهائياً: analysis / interview / story / video.
 * «story» هو موضع عرض في الصفحة الرئيسية، وليس نوع محتوى.
 */
enum ArticleType: string
{
    case News = 'news';
    case Opinion = 'opinion';
    case Live = 'live';

    public function label(): string
    {
        return __('article.type.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
