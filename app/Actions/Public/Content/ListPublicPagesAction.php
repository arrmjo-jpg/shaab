<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Http\Resources\Public\Content\PublicPageResource;
use App\Models\Page;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Cache\PageCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * قائمة الصفحات الثابتة المنشورة (قراءة عامة) — لبناء روابط التنقّل (هيدر/تذييل) أو خريطة
 * الموقع. منشورة فقط + بادئة locale، مرتّبة بـ sort_order. مرشّح اختياري placement
 * (header|footer). كاش single-flight (CachedRead) — الصفحات نادرة التغيّر (TTL طويل).
 */
class ListPublicPagesAction
{
    public function handle(string $locale, Request $request): JsonResponse
    {
        if (! in_array($locale, Page::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $placement = (string) $request->query('placement', '');
        $placement = in_array($placement, ['header', 'footer'], true) ? $placement : '';

        $data = CachedRead::remember(
            PageCacheTags::feedTags($locale),
            CacheKeys::publicPagesList($locale, $placement === '' ? 'all' : $placement),
            CacheTtl::LONG,
            function () use ($locale, $placement): array {
                $query = Page::query()
                    ->published()
                    ->forLocale($locale)
                    ->orderBy('sort_order')
                    ->orderBy('title');

                if ($placement === 'header') {
                    $query->where('show_in_header', true);
                } elseif ($placement === 'footer') {
                    $query->where('show_in_footer', true);
                }

                return PublicPageResource::collection($query->get())->resolve();
            }
        );

        return ApiResponse::success(data: $data);
    }
}
