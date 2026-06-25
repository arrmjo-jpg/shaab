<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\VideoLibrary;

use App\Actions\Public\VideoLibrary\ListFeaturedVideosAction;
use App\Actions\Public\VideoLibrary\ListPublicVideosAction;
use App\Actions\Public\VideoLibrary\ListRelatedVideosAction;
use App\Actions\Public\VideoLibrary\ListTrendingVideosAction;
use App\Actions\Public\VideoLibrary\ListVideosByCategoryAction;
use App\Actions\Public\VideoLibrary\ShowPublicVideoAction;
use App\Http\Controllers\Controller;
use App\Support\Content\PublicSeoBuilder;
use App\Support\Content\VideoRedirectResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * واجهة الفيديو العامة (الجوّال أولاً + الواجهة أولاً). كل النقاط داخل مجموعة
 * public.cache (CDN/ETag) عدا redirect (استجابة 301 خاصّة لا تُخزَّن كمحتوى).
 */
class VideoController extends Controller
{
    public function index(Request $request, string $locale): JsonResponse
    {
        return (new ListPublicVideosAction)->handle($locale, $request);
    }

    public function featured(Request $request, string $locale): JsonResponse
    {
        return (new ListFeaturedVideosAction)->handle($locale, $request);
    }

    public function trending(Request $request, string $locale): JsonResponse
    {
        return (new ListTrendingVideosAction)->handle($locale, $request);
    }

    public function byCategory(Request $request, string $locale, string $slug): JsonResponse
    {
        return (new ListVideosByCategoryAction)->handle($locale, $slug, $request);
    }

    public function show(string $locale, string $slug): JsonResponse
    {
        return (new ShowPublicVideoAction)->handle($locale, $slug);
    }

    public function related(Request $request, string $locale, string $slug): JsonResponse
    {
        return (new ListRelatedVideosAction)->handle($locale, $slug, $request);
    }

    /**
     * مُحلِّل إعادة توجيه 301 (SEO): مسار قانوني قديم كامل (?path=) → الـ canonical
     * الحالي. يستخدمه catch-all الواجهة/الزواحف للروابط القديمة. لا تطابق ⇒ 404.
     */
    public function redirect(Request $request, string $locale): JsonResponse
    {
        $path = (string) $request->query('path', '');
        $target = $path !== '' ? VideoRedirectResolver::resolveByPath($locale, $path) : null;

        if ($target === null) {
            return response()->json(['message' => __('video.not_found')], 404);
        }

        $location = PublicSeoBuilder::absoluteUrl($target->canonicalPath());

        return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
    }
}
