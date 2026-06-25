<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\PlaylistUrlHistory;
use App\Models\VideoPlaylist;
use Illuminate\Support\Str;

/**
 * مُحلِّل إعادة التوجيه 301 لقوائم التشغيل (مرآة VideoRedirectResolver): يربط
 * مساراً/سلَغاً قانونياً قديماً بقائمة التشغيل الحالية القابلة للعرض (منشورة +
 * عامة/غير مُدرَجة). لا توجيه إلى مسودة/مؤرشف/خاص. منع حلقات: لا توجيه إن طابق
 * الهدفُ المطلوبَ. مفهرس (locale, old_path) عبر playlist_url_history.
 */
final class PlaylistRedirectResolver
{
    /** مطابقة مسار قانوني قديم كامل (/{locale}/playlists/{id}-{slug}). */
    public static function resolveByPath(string $locale, string $oldPath): ?VideoPlaylist
    {
        if (! in_array($locale, VideoPlaylist::LOCALES, true)) {
            return null;
        }

        $oldPath = '/'.trim($oldPath, '/');

        $row = PlaylistUrlHistory::query()
            ->where('locale', $locale)
            ->where('old_path', $oldPath)
            ->latest('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $playlist = VideoPlaylist::query()->viewable()->whereKey($row->video_playlist_id)->first();
        if ($playlist === null) {
            return null;
        }

        return $playlist->canonicalPath() === $oldPath ? null : $playlist;
    }

    /** مطابقة بالـ (locale قديم + slug قديم) — لاستهلاك نقطة /{locale}/playlists/{slug}. */
    public static function resolveBySlug(string $locale, string $slug): ?VideoPlaylist
    {
        if (! in_array($locale, VideoPlaylist::LOCALES, true) || $slug === '') {
            return null;
        }

        $rows = PlaylistUrlHistory::query()
            ->where('locale', $locale)
            ->where('old_path', 'like', '%-'.addcslashes($slug, '\\%_'))
            ->latest('id')
            ->limit(20)
            ->get(['video_playlist_id', 'old_path']);

        foreach ($rows as $row) {
            if (self::slugFromPath((string) $row->old_path) !== $slug) {
                continue;
            }

            $playlist = VideoPlaylist::query()->viewable()->whereKey($row->video_playlist_id)->first();
            if ($playlist === null) {
                continue;
            }

            if ($playlist->locale === $locale && $playlist->slug === $slug) {
                return null; // حلقة
            }

            return $playlist;
        }

        return null;
    }

    private static function slugFromPath(string $path): string
    {
        $base = (string) Str::afterLast(trim($path, '/'), '/');

        return (string) preg_replace('/^\d+-/', '', $base);
    }
}
