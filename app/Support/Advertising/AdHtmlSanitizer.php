<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * تنقية HTML للإبداعات. إبداعات HTML طرف-أول موثوقة، لكنها تبقى ضمن قائمة بيضاء صريحة
 * وصارمة. HTMLPurifier قائم على القائمة البيضاء بطبعه: يُزال كل ما ليس مسموحاً صراحةً —
 *   • لا <script> ولا <iframe> ولا <object>/<embed>،
 *   • لا معالِجات أحداث ضمنية (onclick/onload/on*…)،
 *   • لا مخطّطات خطِرة في الروابط (javascript:/data:)،
 *   • وسوم/خصائص/أنماط (style) محصورة عبر config('advertising.creatives.html').
 *
 * تُطبَّق عند الكتابة فقط (إنشاء/تعديل الإبداع)؛ طبقة الخدمة تقرأ html_code المُنقّى مسبقاً.
 */
final class AdHtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function sanitize(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $html = trim($html);
        if ($html === '') {
            return '';
        }

        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier instanceof HTMLPurifier) {
            return self::$purifier;
        }

        /** @var array{allowed_html:string,allowed_css:array<int,string>,allowed_schemes:array<int,string>} $cfg */
        $cfg = config('advertising.creatives.html');

        $config = HTMLPurifier_Config::createDefault();

        // ترميز ثابت + تعطيل كاش التعريفات على القرص (التنقية على مسار الكتابة الإداريّ
        // النادر — لا داعي لمسار قابل للكتابة، والنسخة محفوظة في خاصّية ساكنة لكل عملية).
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.DefinitionImpl', null);

        // القائمة البيضاء الصريحة: الوسوم + الخصائص المسموح بها.
        $config->set('HTML.Allowed', $cfg['allowed_html']);

        // الأنماط المضمّنة (style) — قائمة خصائص CSS بيضاء محصورة.
        $config->set('CSS.AllowedProperties', implode(',', $cfg['allowed_css']));

        // مخطّطات الروابط المسموح بها فقط (href/src) — يمنع javascript:/data:.
        $schemes = [];
        foreach ($cfg['allowed_schemes'] as $scheme) {
            $schemes[$scheme] = true;
        }
        $config->set('URI.AllowedSchemes', $schemes);

        // السماح بـ target="_blank" فقط؛ يُضاف rel="noopener noreferrer" تلقائياً.
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        return self::$purifier = new HTMLPurifier($config);
    }
}
