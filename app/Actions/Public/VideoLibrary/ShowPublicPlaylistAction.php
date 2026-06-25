<?php

declare(strict_types=1);

namespace App\Actions\Public\VideoLibrary;

use App\Http\Resources\Public\VideoLibrary\PublicPlaylistResource;
use App\Models\VideoPlaylist;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\VideoCacheTags;
use App\Support\Content\PlaylistRedirectResolver;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تفاصيل قائمة تشغيل عامة بالـ (locale + slug). قابلة للعرض = منشورة + عامة/غير
 * مُدرَجة. تُضمَّن فيديوهاتها العامة القابلة للتشغيل فقط، مرتّبة بالـ position
 * (لا تسريب مسودة/خاص/غير جاهز). سلَغ قديم → 301 عبر playlist_url_history، وإلا 404.
 * مُوسَم بخلاصة اللغة أيضاً فيُبطَل عند تغيّر حالة أي فيديو عضو.
 */
class ShowPublicPlaylistAction
{
    public function handle(string $locale, string $slug): JsonResponse
    {
        if (! in_array($locale, VideoPlaylist::LOCALES, true)) {
            return ApiResponse::error(__('video.invalid_locale'), [], 422);
        }

        $payload = CachedRead::remember(
            array_merge(VideoCacheTags::feedTags($locale), [VideoCacheTags::playlist($locale, $slug)]),
            CacheKeys::publicPlaylistDetail($locale, $slug),
            CacheTtl::REALTIME,
            function () use ($locale, $slug): ?array {
                $playlist = VideoPlaylist::query()
                    ->viewable()
                    ->forLocale($locale)
                    ->where('slug', $slug)
                    ->with([
                        'cover',
                        // الأعضاء العامون القابلون للتشغيل فقط، بترتيب الـ position المعرّف بالعلاقة.
                        'videos' => fn ($q) => $q->public()->playable()
                            ->with(['mediaAsset', 'category', 'engagementCounter']),
                    ])
                    ->first();

                return $playlist === null ? null : (new PublicPlaylistResource($playlist))->resolve();
            }
        );

        if ($payload === null) {
            $target = PlaylistRedirectResolver::resolveBySlug($locale, $slug);
            if ($target !== null) {
                $location = url("/api/v1/{$target->locale}/playlists/{$target->slug}");

                return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
            }

            return ApiResponse::error(__('video_playlist.not_found'), [], 404);
        }

        return ApiResponse::success(data: $payload);
    }
}
