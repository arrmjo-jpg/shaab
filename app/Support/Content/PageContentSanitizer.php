<?php

declare(strict_types=1);

namespace App\Support\Content;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * تنقية محتوى الصفحات الثابتة (HTML). مرآةُ AdHtmlSanitizer: قائمة بيضاء صريحة عبر
 * HTMLPurifier — يُزال كل ما ليس مسموحاً صراحةً (لا <script>/<iframe>/<object>، لا
 * معالِجات أحداث on*، لا مخطّطات javascript:/data: في الروابط). المحرّر الإداريّ
 * طرف-أول موثوق ومحكوم بالصلاحيات، لكن التنقية دفاع بالعمق على مسار الكتابة.
 *
 * تُطبَّق عند الإنشاء/التعديل فقط؛ الطبقات الأعلى تقرأ content المُنقّى مسبقاً.
 */
final class PageContentSanitizer
{
    /** وسوم/خصائص مسموح بها — محتوى تحريريّ ثابت (عناوين/فقرات/قوائم/روابط/صور/جداول).
     *  محصورة بما يدعمه HTMLPurifier افتراضياً (XHTML 1.0 Transitional). */
    private const ALLOWED_HTML =
        'h2,h3,h4,p,br,hr,strong,em,u,s,blockquote,'.
        'ul,ol,li,a[href|title|target|rel],img[src|alt|width|height],'.
        'span,div,table,thead,tbody,tr,th,td';

    private static ?HTMLPurifier $purifier = null;

    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);
        if ($html === '') {
            return null;
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier instanceof HTMLPurifier) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        // التنقية على مسار كتابة إداريّ نادر — لا حاجة لكاش تعريفات على القرص.
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', self::ALLOWED_HTML);
        // مخطّطات الروابط الآمنة فقط (يمنع javascript:/data:).
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        // السماح بـ target="_blank" فقط؛ يُضاف rel="noopener noreferrer" تلقائياً.
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        return self::$purifier = new HTMLPurifier($config);
    }
}
