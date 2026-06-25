<?php

declare(strict_types=1);

namespace App\Support\Whatsapp;

use App\Models\Article;
use App\Settings\GeneralSettings;

/**
 * مُركِّب نص رسائل واتساب — مصدر واحد للصياغة:
 *   • إعلانية: اسم المرسِل (site_name من الإعدادات) أعلى الرسالة + النص + رابط الموقع أسفلها تلقائياً.
 *   • خبر: *العنوان* + الملخص + رابط الخبر (روابط الواجهة العامة بلا بادئة لغة).
 * النص نفسه يُستخدم body للرسالة النصية أو caption للصورة/الفيديو (رسالة واحدة).
 */
final class WhatsappMessageComposer
{
    /** نص الرسالة الإعلانية النهائي (الأجزاء الفارغة تُسقَط). */
    public static function promo(?string $text): string
    {
        $s = app(GeneralSettings::class);

        return self::join([
            $s->site_name !== '' ? '*'.$s->site_name.'*' : null,
            $text !== null && trim($text) !== '' ? trim($text) : null,
            $s->site_url !== '' ? $s->site_url : null,
        ]);
    }

    /** نص رسالة الخبر: العنوان + الملخص + الرابط — تلقائياً من المقال بلا إدخال يدوي. */
    public static function article(Article $article): string
    {
        return self::join([
            '*'.$article->title.'*',
            trim((string) $article->excerpt) !== '' ? trim((string) $article->excerpt) : null,
            self::articleUrl($article),
        ]);
    }

    /** رابط الخبر العام — نمط روابط الواجهة الفعلية: {site_url}/articles/{id}-{slug} (بلا لغة). */
    public static function articleUrl(Article $article): string
    {
        $base = rtrim(app(GeneralSettings::class)->site_url, '/');

        return $base.'/articles/'.$article->id.'-'.$article->slug;
    }

    /** @param array<int,string|null> $parts */
    private static function join(array $parts): string
    {
        return implode("\n\n", array_values(array_filter($parts, fn (?string $p): bool => $p !== null && $p !== '')));
    }
}
