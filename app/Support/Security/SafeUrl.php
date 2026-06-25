<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * حارس روابط آمنة — يرفض المخططات غير https والمضيفات الداخلية/الخاصة/الاسترجاعية
 * (loopback/private/link-local/metadata/ULA/CGNAT) وصيَغ الـ IP المُبهَمة
 * (عشري/سداسي عشري/ثُماني). يُستخدَم لتقييد:
 *   - نقطة التخزين البعيد (remote_endpoint) التي يتّصل بها الخادم ⇒ منع SSRF.
 *   - روابط الفيديو الخارجي المباشرة (mp4) المضمَّنة في المحتوى.
 *
 * ملاحظة: الحماية الكاملة ضدّ SSRF (خصوصاً إعادة ربط DNS وقت الاتصال) تتطلّب
 * ضوابط خروج على مستوى الشبكة؛ هذا الحارس يحجب التمثيلات الحرفية + صيَغ الإبهام
 * الشائعة ويفرض https، كطبقة تطبيق دفاعية.
 */
final class SafeUrl
{
    /** @var array<int,string> مضيفات/بادئات ممنوعة (دفاع عميق فوق فحص المدى). */
    private const BLOCKED_HOSTS = ['localhost', '127.', '0.0.0.0', '::1', '169.254.', '10.', '192.168.'];

    public static function isPublicHttps(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || str_contains($host, '..')) {
            return false;
        }

        // مضيف IPv6 حرفي يأتي بين قوسين [..] من parse_url.
        $host = trim($host, '[]');

        // نطاقات داخلية شائعة (DNS).
        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        // ── صيَغ IP مُبهَمة (قبل فحص IP): العميل (curl/المتصفّح) قد يفسّر مقطعاً
        //    سداسياً/ثُمانياً كعنوان داخلي بينما يمرّ هنا كـ «عام». نرفض بوضوح. ──
        foreach (explode('.', $host) as $segment) {
            if (preg_match('/^0x[0-9a-f]+$/i', $segment) || preg_match('/^0\d+$/', $segment)) {
                return false; // مقطع سداسي عشري أو ثُماني (بادئة 0) ⇒ إبهام
            }
        }
        // مضيف عدد صحيح مضغوط بالكامل: عشري (2130706433) أو سداسي (0x7f000001).
        if (preg_match('/^\d+$/', $host) || preg_match('/^0x[0-9a-f]+$/i', $host)) {
            return false;
        }

        // عنوان IP حرفي صالح (v4/v6) ⇒ تحقّق المدى مباشرةً.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        // أرقام ونقاط فقط لكنه ليس IPv4 صالحاً (127.1 / 999.1.1.1) ⇒ رفض.
        if (preg_match('/^[0-9.]+$/', $host)
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        // قائمة الحظر الحرفية (دفاع عميق).
        foreach (self::BLOCKED_HOSTS as $blocked) {
            if ($host === $blocked || str_starts_with($host, $blocked)) {
                return false;
            }
        }

        // اسم نطاق عادي — مسموح (إعادة ربط DNS تبقى مسؤولية ضوابط الخروج الشبكية).
        return true;
    }

    /**
     * تحقّق وقت-الاتصال ضدّ إعادة ربط DNS — يحلّ المضيف ويرفض إن لم تكن **كل**
     * العناوين المُحلّاة عامة. مضيف IP حرفي يُفحَص مباشرةً. تعذّر التحليل ⇒ false
     * (fail-safe). يُستخدَم في فاحص صحّة البثّ (probe) قبل الاتصال الفعلي.
     */
    public static function hostResolvesToPublicIp(string $host): bool
    {
        $host = trim(strtolower($host), '[]');
        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        $ips = [];
        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = array_merge($ips, $v4);
        }
        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            return false; // غير قابل للتحليل ⇒ fail-safe
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /** هل عنوان IP الحرفي عام؟ يحجب الخاص/المحجوز + ثغرات شائعة (ULA/CGNAT/mapped). */
    public static function isPublicIp(string $ip): bool
    {
        // IPv4-mapped IPv6 (::ffff:127.0.0.1) ⇒ افحص الـ v4 المضمَّن.
        if (str_contains($ip, '.') && str_contains($ip, ':')) {
            $v4 = substr($ip, strrpos($ip, ':') + 1);
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return self::isPublicIp($v4);
            }
        }

        // الأساس: نطاقات PHP الخاصة + المحجوزة (تغطّي معظم v4/v6).
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // ثغرات قد لا يغطّيها filter_var عبر إصدارات PHP:
        if (preg_match('/^100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\./', $ip)) {
            return false; // CGNAT 100.64.0.0/10
        }
        if (preg_match('/^f[cd][0-9a-f]{2}:/i', $ip)) {
            return false; // IPv6 ULA fc00::/7 (fc../fd..)
        }

        return true;
    }
}
