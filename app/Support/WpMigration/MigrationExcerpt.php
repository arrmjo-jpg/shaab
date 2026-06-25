<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

/**
 * مقتطف SEO حتميّ للمقال المُرحَّل (قاعدة #4). الأولوية لمقتطف ووردبريس الصريح
 * (post_excerpt)؛ وإن غاب يُولَّد من المتن: إزالة الـ shortcodes ثم الوسوم، فكّ
 * الكيانات، تطبيع المسافات، ثم اقتطاع ودود لمحرّكات البحث (~150–170 محرفاً) عند حدّ
 * كلمة. عربيّ-آمن تماماً: كل القصّ عبر دوالّ mb_* بترميز UTF-8 صريح فلا يُقطع محرف
 * متعدّد البايت في منتصفه أبداً.
 */
final class MigrationExcerpt
{
    /** الطول المستهدف للمقتطف (مناسب لمقتطف نتائج البحث). */
    private const TARGET = 160;

    /** أدنى نسبة من الطول المستهدف نقبل عندها القصّ عند حدّ كلمة (وإلا نقصّ عند الطول). */
    private const MIN_WORD_BOUNDARY = 0.6;

    public static function make(?string $wpExcerpt, string $rawHtml): ?string
    {
        $explicit = self::clean((string) $wpExcerpt);
        if ($explicit !== '') {
            return self::cap($explicit);
        }

        $body = self::clean(self::stripShortcodes($rawHtml));

        return $body !== '' ? self::cap($body) : null;
    }

    /** يزيل الـ shortcodes (مثل [gallery ...] و[/caption]) قبل تجريد الوسوم. */
    private static function stripShortcodes(string $s): string
    {
        return (string) preg_replace('/\[\/?[^\]]*\]/u', ' ', $s);
    }

    /** يجرّد الوسوم، يفكّ كيانات HTML، يطبّع المسافات (شاملة المسافات العربية/Unicode). */
    private static function clean(string $s): string
    {
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = (string) preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }

    /** اقتطاع عربيّ-آمن عند حدّ كلمة ضمن الطول المستهدف؛ يُلحِق … عند الاقتطاع. */
    private static function cap(string $s): string
    {
        if (mb_strlen($s, 'UTF-8') <= self::TARGET) {
            return $s;
        }

        $slice = mb_substr($s, 0, self::TARGET, 'UTF-8');
        $lastSpace = mb_strrpos($slice, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace >= (int) (self::TARGET * self::MIN_WORD_BOUNDARY)) {
            $slice = mb_substr($slice, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($slice).'…';
    }
}
