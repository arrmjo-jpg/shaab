<?php

declare(strict_types=1);

namespace App\Actions\Public\VideoLibrary;

use App\Http\Resources\Public\VideoLibrary\PublicVideoResource;
use App\Models\Video;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\VideoCacheTags;
use App\Support\Content\VideoRedirectResolver;
use App\Support\Engagement\EngagementBeaconToken;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تفاصيل فيديو عام بالـ (locale + slug). قابل للعرض = منشور + قابل للتشغيل + عام
 * أو غير مُدرَج (unlisted يُفتح بالرابط). يستبعد المسودة/المؤرشف/الخاص/الوسائط غير
 * الجاهزة. بحث بالـ slug ضمن نطاق اللغة. سلَغ قديم → 301.
 *
 * الاحتساب لا يتمّ هنا (الاستجابة قابلة للتخزين على الـ CDN): تُصدِر meta.view_token
 * موقّعاً، ويسجّل العميل المشاهدة عبر منارة /engagement/video/{id}/view غير المُخزّنة.
 */
class ShowPublicVideoAction
{
    public function handle(string $locale, string $slug): JsonResponse
    {
        if (! in_array($locale, Video::LOCALES, true)) {
            return ApiResponse::error(__('video.invalid_locale'), [], 422);
        }

        $payload = CachedRead::remember(
            VideoCacheTags::detailTags($locale, $slug),
            CacheKeys::publicVideoDetail($locale, $slug),
            CacheTtl::REALTIME,
            function () use ($locale, $slug): ?array {
                $video = Video::query()
                    ->viewable()
                    ->forLocale($locale)
                    ->where('slug', $slug)
                    ->with(['mediaAsset', 'category', 'engagementCounter'])
                    ->first();

                return $video === null ? null : (new PublicVideoResource($video))->resolve();
            }
        );

        if ($payload === null) {
            // SEO: slug/locale قديم → 301 إلى رابط الفيديو الحالي (منع حلقة مضمون).
            $target = VideoRedirectResolver::resolveBySlug($locale, $slug);
            if ($target !== null) {
                $location = url("/api/v1/{$target->locale}/videos/{$target->slug}");

                return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
            }

            return ApiResponse::error(__('video.not_found'), [], 404);
        }

        // رمز منارة موقّع (طازج كل طلب، خارج الكاش) — العميل يسجّل المشاهدة عبره.
        return ApiResponse::success(
            data: $payload,
            meta: ['view_token' => EngagementBeaconToken::issue('video', (int) $payload['id'])],
        );
    }
}
