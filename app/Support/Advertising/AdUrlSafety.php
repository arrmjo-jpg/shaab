<?php

declare(strict_types=1);

namespace App\Support\Advertising;

/**
 * سلامة روابط الإعلان — يمنع open redirect وحقن المخطّطات (javascript:/data:). يُطبَّق
 * عند إنشاء/تعديل الإبداع (تحقّق) وعند تحويل النقرة (إعادة تحقّق دفاع-بالعمق). التحويل
 * يستخدم landing_url المُخزَّن فقط — لا يُعاد توجيه أبداً لرابط يمرّره العميل.
 */
final class AdUrlSafety
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public static function isSafe(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        return in_array($scheme, self::ALLOWED_SCHEMES, true) && $host !== '';
    }

    /** هدف تحويل آمن أو null (لا open redirect). */
    public static function safeTarget(?string $url): ?string
    {
        return self::isSafe($url) ? trim((string) $url) : null;
    }
}
