<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Http\Resources\Public\Content\PublicReelResource;
use App\Models\Reel;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\ReelCacheTags;
use App\Support\Content\ReelRedirectResolver;
use App\Support\Engagement\EngagementBeaconToken;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تفاصيل ريل عام بالـ (locale + slug). منشور فقط (status=published وtime<=now).
 * بحث بالـ slug (لا id) ضمن نطاق اللغة. احتساب مشاهدة موحّد خارج الكاش.
 */
class ShowPublicReelAction
{
    public function handle(string $locale, string $slug): JsonResponse
    {
        if (! in_array($locale, Reel::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $payload = CachedRead::remember(
            ReelCacheTags::detailTags($locale, $slug),
            CacheKeys::publicReelDetail($locale, $slug),
            CacheTtl::REALTIME,
            function () use ($locale, $slug): ?array {
                $reel = Reel::query()
                    ->published()
                    ->forLocale($locale)
                    ->where('slug', $slug)
                    ->with(['mediaAsset', 'engagementCounter'])
                    ->first();

                return $reel === null ? null : (new PublicReelResource($reel))->resolve();
            }
        );

        if ($payload === null) {
            // SEO: slug/locale قديم → 301 إلى رابط الريل الحالي (منع حلقة مضمون).
            $target = ReelRedirectResolver::resolveBySlug($locale, $slug);
            if ($target !== null) {
                $location = url("/api/v1/{$target->locale}/reels/{$target->slug}");

                return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
            }

            return ApiResponse::error(__('reel.not_found'), [], 404);
        }

        // رمز منارة موقّع (طازج كل طلب، خارج الكاش) — العميل يسجّل المشاهدة عبره
        // (احتساب دقيق خلف الـ CDN؛ لا احتساب على طلب التفاصيل القابل للتخزين).
        return ApiResponse::success(
            data: $payload,
            meta: ['view_token' => EngagementBeaconToken::issue('reel', (int) $payload['id'])],
        );
    }
}
