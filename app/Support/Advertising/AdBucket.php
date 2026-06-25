<?php

declare(strict_types=1);

namespace App\Support\Advertising;

/**
 * الدلو الزمني للتدوير الثابت (sticky time-bucket) — يجعل العرض صديقاً للـ CDN مع
 * إبقاء الاختيار في الخادم: الاختيار حتميّ ضمن الدلو، ويتدوّر بشكل متوقّع بين الدلاء.
 *
 * البذرة تشمل أبعاد التجزئة (مساحة، لغة، جهاز) + رقم الدلو — فيتطابق الاختيار عبر
 * كل عُقد الحافة/العمّال لنفس الدلو (لا تباين)، ويتوزّع بعدالة عبر الزمن.
 */
final class AdBucket
{
    public static function window(): int
    {
        return max(1, (int) config('advertising.serve.bucket_window', 30));
    }

    /** رقم الدلو الحاليّ = ثوانٍ منذ الحقبة ÷ نافذة الدلو. */
    public static function current(?int $window = null): int
    {
        return intdiv(time(), max(1, $window ?? self::window()));
    }

    /** بذرة الاختيار الحتميّ: {zone}:{locale}:{device}:bucket_{n}. */
    public static function seed(string $zoneKey, string $locale, string $device, int $bucket): string
    {
        return $zoneKey.':'.$locale.':'.$device.':bucket_'.$bucket;
    }
}
