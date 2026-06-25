<?php

declare(strict_types=1);

namespace App\Support\Cache;

/**
 * وسوم كاش الإعلانات — مصدر الحقيقة للإبطال الحبيبي (granular). يُجزَّأ مفتاح بِركة
 * الخدمة بـ (zone, locale, device) لكن الإبطال يكون على مستوى المساحة: تفريغ وسم
 * المساحة يُسقط كل تنويعات اللغة/الجهاز معاً — إبطال بسيط وموثوق.
 *
 *   ALL          → مظلّة عامة (صيانة/تفريغ شامل).
 *   zone(key)    → كل بِرَك مساحة واحدة (كل اللغات/الأجهزة).
 *
 * يتطلّب مخزناً يدعم الوسوم (Redis إنتاجاً — إلزامي عبر RedisProductionCheck).
 */
final class AdCacheTags
{
    public const ALL = 'ads';

    public static function zone(string $zoneKey): string
    {
        return 'ads:zone:'.$zoneKey;
    }

    /** @return array<int,string> وسوم إدخال بِركة مساحة. */
    public static function zoneTags(string $zoneKey): array
    {
        return [self::ALL, self::zone($zoneKey)];
    }

    /**
     * وسوم الإبطال عند كتابة تمسّ مساحة — مع المساحة القديمة عند نقل الإسناد
     * (يمنع بقاء بِركة قديمة تحت مفتاح مساحة سابقة).
     *
     * @return array<int,string>
     */
    public static function invalidationTags(string $zoneKey, ?string $oldZoneKey = null): array
    {
        $tags = self::zoneTags($zoneKey);

        if ($oldZoneKey !== null && $oldZoneKey !== '' && $oldZoneKey !== $zoneKey) {
            $tags[] = self::zone($oldZoneKey);
        }

        return array_values(array_unique($tags));
    }
}
