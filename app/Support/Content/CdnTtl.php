<?php

declare(strict_types=1);

namespace App\Support\Content;

use Illuminate\Support\Carbon;

/**
 * استراتيجية TTL متمايزة لحافة الـ CDN حسب نوع المحتوى الإخباري:
 *
 *   عاجل (breaking)  → TTL قصير جداً (طزاجة فورية، لا إحراج تحريري).
 *   قوائم/رئيسية      → TTL متوسّط (افتراضي البوّابة عبر PublicCacheHeaders).
 *   تفاصيل حديثة      → TTL متوسّط-طويل (المحتوى مستقرّ؛ الإبطال الحبيبي يبطِل الحافة عند التعديل).
 *   أرشيف (قديم)      → TTL طويل جداً (نادر التغيّر).
 *
 * يبني سلسلة Cache-Control كاملة؛ يُطبَّق على مستوى الـ Action (PublicCacheHeaders
 * يحترم Cache-Control المضبوط مسبقاً ولا يدهسه). stale-while-revalidate يبقي الحافة
 * تخدم نسخة قديمة لحظة التحديث في الخلفية — لا فراغ ولا إحراج.
 */
final class CdnTtl
{
    /** عتبة اعتبار المقال «أرشيفاً» (بالأيام). */
    private const ARCHIVE_AFTER_DAYS = 30;

    public static function breaking(): string
    {
        return self::build(maxAge: 15, sMaxAge: 45, swr: 120);
    }

    public static function detail(): string
    {
        return self::build(maxAge: 120, sMaxAge: 1800, swr: 86400);
    }

    public static function archive(): string
    {
        return self::build(maxAge: 600, sMaxAge: 86400, swr: 604800);
    }

    /** يختار تفاصيل/أرشيف حسب عمر النشر (ISO string أو null). */
    public static function forPublishedAt(?string $isoPublishedAt): string
    {
        if ($isoPublishedAt !== null
            && Carbon::parse($isoPublishedAt)->lt(now()->subDays(self::ARCHIVE_AFTER_DAYS))) {
            return self::archive();
        }

        return self::detail();
    }

    private static function build(int $maxAge, int $sMaxAge, int $swr): string
    {
        return sprintf(
            'public, max-age=%d, s-maxage=%d, stale-while-revalidate=%d',
            $maxAge,
            $sMaxAge,
            $swr,
        );
    }
}
