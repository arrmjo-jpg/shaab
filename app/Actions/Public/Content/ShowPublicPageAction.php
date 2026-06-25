<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Http\Resources\Public\Content\PublicPageResource;
use App\Models\Page;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\PageCacheTags;
use App\Support\Content\PageRedirectResolver;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تفاصيل صفحة ثابتة عامة بالـ (locale + slug). منشورة فقط (status=published وزمن<=now).
 * بحث بالـ slug ضمن نطاق اللغة. كاش single-flight (CachedRead) ضدّ عاصفة الطوابير.
 */
class ShowPublicPageAction
{
    public function handle(string $locale, string $slug): JsonResponse
    {
        if (! in_array($locale, Page::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $payload = CachedRead::remember(
            PageCacheTags::detailTags($locale, $slug),
            CacheKeys::publicPageDetail($locale, $slug),
            CacheTtl::LONG,
            function () use ($locale, $slug): ?array {
                $page = Page::query()
                    ->published()
                    ->forLocale($locale)
                    ->where('slug', $slug)
                    ->first();

                return $page === null ? null : (new PublicPageResource($page))->resolve();
            }
        );

        if ($payload === null) {
            // SEO: slug/locale قديم → 301 إلى رابط الصفحة الحالية (منع حلقة مضمون
            // في الـ resolver). الرد خارج الكاش (طازج كل طلب فاشل) — حالة نادرة.
            $target = PageRedirectResolver::resolveBySlug($locale, $slug);
            if ($target !== null) {
                $location = url("/api/v1/{$target->locale}/pages/{$target->slug}");

                return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
            }

            return ApiResponse::error(__('page.not_found'), [], 404);
        }

        return ApiResponse::success(data: $payload);
    }
}
