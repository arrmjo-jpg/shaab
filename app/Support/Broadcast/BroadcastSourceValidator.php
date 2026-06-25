<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

use App\Support\Security\SafeUrl;

/**
 * تحقّق إنتاجي من مصدر بثّ خارجي — طبقتان دفاعيّتان (مرآة Mp4HostAllowList):
 *   1) SafeUrl::isPublicHttps — https + مضيف عام (يمنع loopback/خاص/CGNAT/ULA
 *      وصيَغ الـ IP المُبهَمة) ⇒ خطّ الدفاع الأول ضدّ SSRF.
 *   2) allow-list مضيفات موثوقة لكل نوع مصدر (config('broadcast.allowed_hosts'))
 *      — مطابقة المضيف نفسه أو كنطاق فرعي. فارغة ⇒ رفض الكلّ (افتراضي آمن).
 *
 * لا روابط عشوائية، لا غير-https، لا مضيف خارج القائمة الموثوقة.
 * (حماية إعادة-التوجيه/إعادة-ربط DNS وقت الفحص الفعلي تُضاف في عميل الـ probe — B3.)
 */
final class BroadcastSourceValidator
{
    public static function isAllowed(string $sourceType, string $url): bool
    {
        $url = trim($url);
        if ($url === '' || ! SafeUrl::isPublicHttps($url)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = trim($host, '[]');
        if ($host === '') {
            return false;
        }

        $allowed = self::allowedHosts($sourceType);
        if ($allowed === []) {
            return false; // افتراضي آمن: لا قائمة موثوقة ⇒ رفض
        }

        foreach ($allowed as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate !== '' && ($host === $candidate || str_ends_with($host, '.'.$candidate))) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> */
    private static function allowedHosts(string $sourceType): array
    {
        $map = (array) config('broadcast.allowed_hosts', []);

        return array_values((array) ($map[$sourceType] ?? []));
    }
}
