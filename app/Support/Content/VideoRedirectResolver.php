<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Video;
use App\Models\VideoUrlHistory;
use Illuminate\Support\Str;

/**
 * مُحلِّل إعادة التوجيه 301 للفيديو (مرآة ReelRedirectResolver): يربط مساراً/سلَغاً
 * قانونياً قديماً بالفيديو الحالي القابل للعرض. الهدف يجب أن يكون قابلاً للعرض فعلاً
 * (منشور + قابل للتشغيل + عام/غير مُدرَج) — فلا نوجّه أبداً إلى مسودة/مؤرشف/خاص أو
 * وسائط غير جاهزة. منع الحلقات: لا توجيه إن طابق الهدفُ المطلوبَ. مفهرس (locale, old_path).
 */
final class VideoRedirectResolver
{
    /** مطابقة مسار قانوني قديم كامل (/{locale}/videos/{id}-{slug}) — مفهرس O(1). */
    public static function resolveByPath(string $locale, string $oldPath): ?Video
    {
        if (! in_array($locale, Video::LOCALES, true)) {
            return null;
        }

        $oldPath = '/'.trim($oldPath, '/');

        $row = VideoUrlHistory::query()
            ->where('locale', $locale)
            ->where('old_path', $oldPath)
            ->latest('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $video = Video::query()->viewable()->whereKey($row->video_id)->first();
        if ($video === null) {
            return null;
        }

        return $video->canonicalPath() === $oldPath ? null : $video;
    }

    /** مطابقة بالـ (locale قديم + slug قديم) — لاستهلاك نقطة /{locale}/videos/{slug}. */
    public static function resolveBySlug(string $locale, string $slug): ?Video
    {
        if (! in_array($locale, Video::LOCALES, true) || $slug === '') {
            return null;
        }

        $rows = VideoUrlHistory::query()
            ->where('locale', $locale)
            ->where('old_path', 'like', '%-'.addcslashes($slug, '\\%_'))
            ->latest('id')
            ->limit(20)
            ->get(['video_id', 'old_path']);

        foreach ($rows as $row) {
            if (self::slugFromPath((string) $row->old_path) !== $slug) {
                continue;
            }

            $video = Video::query()->viewable()->whereKey($row->video_id)->first();
            if ($video === null) {
                continue;
            }

            if ($video->locale === $locale && $video->slug === $slug) {
                return null; // حلقة
            }

            return $video;
        }

        return null;
    }

    private static function slugFromPath(string $path): string
    {
        $base = (string) Str::afterLast(trim($path, '/'), '/');

        return (string) preg_replace('/^\d+-/', '', $base);
    }
}
