<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Enums\VideoStatus;
use App\Http\Resources\Admin\VideoLibrary\VideoPlaylistResource;
use App\Models\PlaylistUrlHistory;
use App\Models\VideoPlaylist;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تعديل قائمة تشغيل. عند تغيّر slug/locale يُسجَّل المسار القديم في
 * playlist_url_history لإعادة التوجيه 301 (تماماً كالفيديو/المقال/الريل).
 */
class UpdateVideoPlaylistAction
{
    public function handle(VideoPlaylist $playlist, array $validated): JsonResponse
    {
        $oldLocale = $playlist->locale;
        $oldSlug = (string) $playlist->slug;
        $oldPath = $playlist->canonicalPath();

        $playlist = DB::transaction(function () use ($playlist, $validated, $oldLocale, $oldSlug, $oldPath): VideoPlaylist {
            foreach (['title', 'locale', 'description', 'cover_media_id', 'visibility', 'sort_order',
                'seo_title', 'seo_description', 'seo_keywords', 'canonical_url', 'robots', 'author_id'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $playlist->{$field} = $validated[$field];
                }
            }

            if (array_key_exists('is_featured', $validated)) {
                $playlist->is_featured = (bool) $validated['is_featured'];
            }

            if (array_key_exists('slug', $validated) && ! empty($validated['slug'])) {
                $playlist->slug = $validated['slug'];
            }

            // حالة القائمة (لا حارس وسائط — القوائم بلا وسائط تشغيل).
            if (array_key_exists('status', $validated)) {
                $playlist->status = $validated['status'];
                if ($playlist->status === VideoStatus::Published->value && $playlist->published_at === null) {
                    $playlist->published_at = now();
                }
            }
            if (array_key_exists('published_at', $validated)) {
                $playlist->published_at = $validated['published_at'];
            }

            $playlist->save();

            if ($playlist->locale !== $oldLocale || (string) $playlist->slug !== $oldSlug) {
                PlaylistUrlHistory::firstOrCreate(
                    ['locale' => $oldLocale, 'old_path' => $oldPath],
                    ['video_playlist_id' => $playlist->id, 'reason' => 'slug_or_locale_change'],
                );
            }

            return $playlist;
        });

        $tags = VideoCacheTags::playlistInvalidationTags($playlist, oldLocale: $oldLocale, oldSlug: $oldSlug);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(
            __('video_playlist.updated'),
            new VideoPlaylistResource($playlist->fresh())
        );
    }
}
