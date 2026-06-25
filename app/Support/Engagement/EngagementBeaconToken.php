<?php

declare(strict_types=1);

namespace App\Support\Engagement;

/**
 * رمز منارة المشاهدة الموقّع (HMAC) — يربط نبضة المنارة بصفحة محتوى صدرت فعلاً.
 *
 * يُصدَر داخل استجابة التفاصيل (meta.view_token) ويُعاد إرساله إلى نقطة المنارة.
 * مقاومة الإساءة:
 *   - توقيع HMAC بمفتاح التطبيق ⇒ لا يمكن تزوير رمز لمحتوى لم تُطلَب صفحته.
 *   - مربوط بـ (type, id) ⇒ لا يصلح رمز لهدف آخر.
 *   - انتهاء صلاحية قصير ⇒ لا إعادة استخدام لاحقة.
 * الرمز غير سرّي وغير مرتبط بفاعل (قد يُخزَّن على الحافة) — منع التكرار لكل فاعل
 * وتصفية البوتات وحدّ المعدّل تبقى مسؤولية الخدمة/المسار، لا الرمز.
 */
final class EngagementBeaconToken
{
    public static function issue(string $type, int $id): string
    {
        $exp = now()->getTimestamp() + self::ttl();
        $payload = $type.':'.$id.':'.$exp;

        return self::b64($payload).'.'.self::sign($payload);
    }

    public static function verify(string $token, string $type, int $id): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        $payload = self::unb64($parts[0]);
        if ($payload === null) {
            return false;
        }

        // مقارنة ثابتة الزمن للتوقيع (مقاومة هجمات التوقيت).
        if (! hash_equals(self::sign($payload), $parts[1])) {
            return false;
        }

        $segments = explode(':', $payload);
        if (count($segments) !== 3) {
            return false;
        }
        [$tokenType, $tokenId, $exp] = $segments;

        return $tokenType === $type
            && (int) $tokenId === $id
            && (int) $exp >= now()->getTimestamp();
    }

    private static function sign(string $payload): string
    {
        return self::b64(hash_hmac('sha256', $payload, (string) config('app.key'), true));
    }

    private static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function unb64(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private static function ttl(): int
    {
        return (int) config('performance.view_beacon.ttl', 3600);
    }
}
