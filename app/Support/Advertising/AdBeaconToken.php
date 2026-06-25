<?php

declare(strict_types=1);

namespace App\Support\Advertising;

/**
 * رمز منارة الإعلان الموقّع (HMAC) — يُصدَر داخل استجابة العرض ويُعاد إلى نقطة التتبّع.
 *
 * الربط: (placement, zone, bucket, exp). مقاومة إعادة التشغيل بطبقتين:
 *   - bucket: الرمز صالح لدلوه فقط (± دلو واحد سماحاً لتأخّر العرض) ⇒ لا يُعاد في دلو لاحق.
 *   - exp: انتهاء صلاحية قصير.
 * الرمز غير سرّي وغير مرتبط بفاعل (قد يُخزَّن على الحافة) — منع التكرار لكل فاعل وحدّ
 * المعدّل وتصفية البوتات تبقى مسؤولية المُتتبِّع/المسار (AdTracker)، لا الرمز.
 */
final class AdBeaconToken
{
    public static function issue(int $placementId, int $zoneId, int $bucket): string
    {
        $exp = now()->getTimestamp() + self::ttl();
        $payload = self::payload($placementId, $zoneId, $bucket, $exp);

        return self::b64($payload).'.'.self::sign($payload);
    }

    public static function verify(string $token, int $placementId, int $zoneId, ?int $currentBucket = null): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        $payload = self::unb64($parts[0]);
        if ($payload === null) {
            return false;
        }

        // مقارنة ثابتة الزمن للتوقيع.
        if (! hash_equals(self::sign($payload), $parts[1])) {
            return false;
        }

        $seg = explode(':', $payload);
        if (count($seg) !== 4) {
            return false;
        }
        [$p, $z, $b, $e] = [(int) $seg[0], (int) $seg[1], (int) $seg[2], (int) $seg[3]];

        if ($p !== $placementId || $z !== $zoneId) {
            return false;
        }
        if ($e < now()->getTimestamp()) {
            return false; // منتهٍ
        }

        $currentBucket ??= AdBucket::current();

        // مقاومة إعادة التشغيل عبر الدلاء: صالح للدلو الحاليّ والسابق فقط.
        return $b >= $currentBucket - 1 && $b <= $currentBucket;
    }

    /**
     * يفكّ الرمز ويتحقّق منه دون معرفة (placement, zone) مسبقاً — لمسار النقرة حيث
     * الرمز وحده في الرابط. يُعيد الحمولة المُتحقَّق منها أو null.
     *
     * @return array{placement_id:int,zone_id:int,bucket:int}|null
     */
    public static function verifyAndDecode(string $token, ?int $currentBucket = null): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        $payload = self::unb64($parts[0]);
        if ($payload === null || ! hash_equals(self::sign($payload), $parts[1])) {
            return null;
        }

        $seg = explode(':', $payload);
        if (count($seg) !== 4) {
            return null;
        }
        [$p, $z, $b, $e] = [(int) $seg[0], (int) $seg[1], (int) $seg[2], (int) $seg[3]];

        if ($e < now()->getTimestamp()) {
            return null; // منتهٍ
        }

        $currentBucket ??= AdBucket::current();
        if ($b < $currentBucket - 1 || $b > $currentBucket) {
            return null; // خارج نافذة الدلو (إعادة تشغيل)
        }

        return ['placement_id' => $p, 'zone_id' => $z, 'bucket' => $b];
    }

    private static function payload(int $p, int $z, int $b, int $e): string
    {
        return $p.':'.$z.':'.$b.':'.$e;
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
        return (int) config('advertising.tracking.beacon_ttl', 3600);
    }
}
