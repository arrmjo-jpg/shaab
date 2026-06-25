<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

/**
 * رمز جلسة الحضور الموقّع (HMAC) — مرآة EngagementBeaconToken. يربط نبضات الحضور
 * بهوية عضو ثابتة صدرت عبر /join، فيمنع تضخيم العدّاد بالتلفيق:
 *   - توقيع HMAC بمفتاح التطبيق ⇒ لا يمكن اختلاق عضو دون المرور بـ join (محدود المعدّل).
 *   - مربوط بـ broadcastId ⇒ لا يصلح رمز لبثّ آخر.
 *   - انتهاء صلاحية ⇒ إعادة الطلب عبر join (إعادة اتصال آمنة).
 *
 * يحمل (broadcastId, member, type). غير سرّي؛ يُخزَّن على العميل ويُعاد في كل نبضة.
 * إعادة الاستخدام بنفس العضو حميدة (تُنعِش حضور العضو نفسه فقط — لا تضخيم)، فلا
 * حاجة لمقاومة إعادة تشغيل؛ منع التكرار/الحدّ مسؤولية المسار، لا الرمز.
 */
final class PresenceSessionToken
{
    public static function issue(int $broadcastId, string $member, string $type): string
    {
        $exp = now()->getTimestamp() + self::ttl();
        $payload = $broadcastId.':'.$member.':'.$type.':'.$exp;

        return self::b64($payload).'.'.self::sign($payload);
    }

    /**
     * يتحقّق من الرمز لبثّ محدّد؛ يعيد هوية العضو أو null عند الفشل/الانتهاء.
     *
     * @return array{member:string,type:string}|null
     */
    public static function verify(string $token, int $broadcastId): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        $payload = self::unb64($parts[0]);
        if ($payload === null) {
            return null;
        }

        // مقارنة ثابتة الزمن (مقاومة هجمات التوقيت).
        if (! hash_equals(self::sign($payload), $parts[1])) {
            return null;
        }

        $segments = explode(':', $payload);
        if (count($segments) !== 4) {
            return null;
        }
        [$bid, $member, $type, $exp] = $segments;

        if ((int) $bid !== $broadcastId
            || $member === ''
            || ! in_array($type, ['guest', 'auth'], true)
            || (int) $exp < now()->getTimestamp()) {
            return null;
        }

        return ['member' => $member, 'type' => $type];
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
        return max(60, (int) config('broadcast.presence.token_ttl', 1800));
    }
}
