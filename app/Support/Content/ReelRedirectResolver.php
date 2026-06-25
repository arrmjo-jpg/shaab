<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Reel;
use App\Models\ReelUrlHistory;
use Illuminate\Support\Str;

/**
 * مُحلِّل إعادة التوجيه 301 للريلز (مرآة ArticleRedirectResolver): يربط مساراً/سلَغاً
 * قديماً بالريل المنشور الحالي. منع الحلقات: لا توجيه إن كان الهدف مطابقاً للمطلوب
 * أو إن لم يَعُد الريل منشوراً. البحث مفهرس على (locale, old_path).
 */
final class ReelRedirectResolver
{
    /** مطابقة مسار قانوني قديم كامل (/{locale}/reels/{id}-{slug}) — مفهرس O(1). */
    public static function resolveByPath(string $locale, string $oldPath): ?Reel
    {
        if (! in_array($locale, Reel::LOCALES, true)) {
            return null;
        }

        $oldPath = '/'.trim($oldPath, '/');

        $row = ReelUrlHistory::query()
            ->where('locale', $locale)
            ->where('old_path', $oldPath)
            ->latest('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $reel = Reel::query()->published()->whereKey($row->reel_id)->first();
        if ($reel === null) {
            return null;
        }

        return $reel->canonicalPath() === $oldPath ? null : $reel;
    }

    /** مطابقة بالـ (locale قديم + slug قديم) — لاستهلاك نقطة /{locale}/reels/{slug}. */
    public static function resolveBySlug(string $locale, string $slug): ?Reel
    {
        if (! in_array($locale, Reel::LOCALES, true) || $slug === '') {
            return null;
        }

        $rows = ReelUrlHistory::query()
            ->where('locale', $locale)
            ->where('old_path', 'like', '%-'.addcslashes($slug, '\\%_'))
            ->latest('id')
            ->limit(20)
            ->get(['reel_id', 'old_path']);

        foreach ($rows as $row) {
            if (self::slugFromPath((string) $row->old_path) !== $slug) {
                continue;
            }

            $reel = Reel::query()->published()->whereKey($row->reel_id)->first();
            if ($reel === null) {
                continue;
            }

            if ($reel->locale === $locale && $reel->slug === $slug) {
                return null; // حلقة
            }

            return $reel;
        }

        return null;
    }

    private static function slugFromPath(string $path): string
    {
        $base = (string) Str::afterLast(trim($path, '/'), '/');

        return (string) preg_replace('/^\d+-/', '', $base);
    }
}
