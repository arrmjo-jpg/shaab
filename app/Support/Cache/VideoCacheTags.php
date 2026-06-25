<?php

declare(strict_types=1);

namespace App\Support\Cache;

use App\Models\Video;
use App\Models\VideoPlaylist;

/**
 * وسوم كاش مكتبة الفيديو — مصدر الحقيقة للإبطال الحبيبي (granular). مرآة
 * ReelCacheTags مع إضافة وسمَي التصنيف وقائمة التشغيل (نطاق أوسع من الريلز).
 *
 *   ALL                    → مظلّة عامة على كل إدخال (تفريغ شامل/صيانة).
 *   feed(locale)           → خلاصات اللغة (قوائم/مميَّز/رائج).
 *   detail(locale, slug)   → تفاصيل فيديو واحد.
 *   category(locale, slug) → صفحة/قائمة تصنيف فيديو.
 *   playlist(locale, slug) → صفحة قائمة تشغيل.
 *   SITEMAP                → خرائط الفيديو (تُبطَل عند أي كتابة منشورة).
 *
 * يتطلّب مخزناً يدعم الوسوم (redis إنتاجاً) — إلزامي أصلاً (RedisProductionCheck).
 */
final class VideoCacheTags
{
    public const ALL = 'videos';

    public const SITEMAP = 'videos:sitemap';

    public static function feed(string $locale): string
    {
        return 'videos:feed:'.$locale;
    }

    public static function detail(string $locale, string $slug): string
    {
        return 'videos:detail:'.$locale.':'.$slug;
    }

    public static function category(string $locale, string $categorySlug): string
    {
        return 'videos:category:'.$locale.':'.$categorySlug;
    }

    public static function playlist(string $locale, string $slug): string
    {
        return 'videos:playlist:'.$locale.':'.$slug;
    }

    /** @return array<int,string> وسوم إدخال خلاصة لغة. */
    public static function feedTags(string $locale): array
    {
        return [self::ALL, self::feed($locale)];
    }

    /** @return array<int,string> وسوم إدخال تفاصيل فيديو (مظلّة + تفاصيله فقط). */
    public static function detailTags(string $locale, string $slug): array
    {
        return [self::ALL, self::detail($locale, $slug)];
    }

    /** @return array<int,string> وسوم صفحة/قائمة تصنيف. */
    public static function categoryTags(string $locale, string $categorySlug): array
    {
        return [self::ALL, self::category($locale, $categorySlug)];
    }

    /** @return array<int,string> وسوم صفحة قائمة تشغيل. */
    public static function playlistTags(string $locale, string $slug): array
    {
        return [self::ALL, self::playlist($locale, $slug)];
    }

    /**
     * الوسوم الواجب إبطالها عند كتابة/تحوّل فيديو — feed لغته + تفاصيله + تصنيفه
     * + SITEMAP، وعند تغيّر اللغة/الـ slug يشمل القديم أيضاً (يمنع بقايا قديمة).
     *
     * @return array<int,string>
     */
    public static function invalidationTags(
        Video $video,
        ?string $oldLocale = null,
        ?string $oldSlug = null,
        ?string $categorySlug = null,
        ?string $oldCategorySlug = null,
    ): array {
        $locale = $video->locale;
        $slug = (string) $video->slug;

        $tags = [self::SITEMAP, self::feed($locale), self::detail($locale, $slug)];
        if ($categorySlug !== null && $categorySlug !== '') {
            $tags[] = self::category($locale, $categorySlug);
        }

        $oldLocale ??= $locale;
        $oldSlug ??= $slug;

        if ($oldLocale !== $locale) {
            $tags[] = self::feed($oldLocale);
        }
        if ($oldLocale !== $locale || $oldSlug !== $slug) {
            $tags[] = self::detail($oldLocale, $oldSlug);
        }
        if ($oldCategorySlug !== null && $oldCategorySlug !== '') {
            $tags[] = self::category($oldLocale, $oldCategorySlug);
        }

        return array_values(array_unique($tags));
    }

    /**
     * وسوم إبطال كتابة قائمة تشغيل — صفحتها + feed لغتها + SITEMAP، مع القديم
     * عند تغيّر slug/locale.
     *
     * @return array<int,string>
     */
    public static function playlistInvalidationTags(
        VideoPlaylist $playlist,
        ?string $oldLocale = null,
        ?string $oldSlug = null,
    ): array {
        $locale = $playlist->locale;
        $slug = (string) $playlist->slug;

        $tags = [self::SITEMAP, self::feed($locale), self::playlist($locale, $slug)];

        $oldLocale ??= $locale;
        $oldSlug ??= $slug;

        if ($oldLocale !== $locale || $oldSlug !== $slug) {
            $tags[] = self::playlist($oldLocale, $oldSlug);
        }

        return array_values(array_unique($tags));
    }
}
