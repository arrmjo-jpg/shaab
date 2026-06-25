<?php

declare(strict_types=1);

namespace App\Support\Content;

use Illuminate\Support\Str;

/**
 * مولّد slug موحّد (Arabic-safe) — مصدر الحقيقة لتطبيع المسارات.
 *
 * يعالج: المسافات، علامات الترقيم، الفواصل المكرّرة، الأحرف الخاصّة،
 * مع الحفاظ على الحروف العربية واليونيكود (لا transliteration للعربية).
 * يضمن قيمة غير فارغة عبر fallback عند تعذّر التطبيع.
 *
 * الفرادة تُدار في طبقة النموذج (eloquent-sluggable + scope per-locale).
 */
final class SlugGenerator
{
    /** تطبيع خام — قد يُرجع سلسلة فارغة إذا لم يبقَ محرف صالح. */
    public static function make(string $value, string $separator = '-'): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // أحرف لاتينية → صغيرة (العربية لا تتأثّر)
        $value = mb_strtolower($value, 'UTF-8');

        $sep = preg_quote($separator, '/');

        // المسافات (وأيّ فراغ) → فاصل
        $value = preg_replace('/\s+/u', $separator, $value) ?? '';
        // إسقاط كل ما ليس حرفاً/رقماً/فاصلاً (يشمل الترقيم والرموز)
        $value = preg_replace('/[^\p{L}\p{N}'.$sep.']+/u', '', $value) ?? '';
        // ضغط الفواصل المكرّرة
        $value = preg_replace('/'.$sep.'+/u', $separator, $value) ?? '';

        return trim($value, $separator);
    }

    /** تطبيع مع fallback مضمون غير فارغ (transliteration لاتيني ثم رمز افتراضي). */
    public static function makeWithFallback(string $value, string $separator = '-'): string
    {
        $slug = self::make($value, $separator);
        if ($slug !== '') {
            return $slug;
        }

        $fallback = Str::slug($value, $separator);

        return $fallback !== '' ? $fallback : 'item';
    }
}
