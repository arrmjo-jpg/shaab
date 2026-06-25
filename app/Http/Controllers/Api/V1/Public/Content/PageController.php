<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Public\Content\ListPublicPagesAction;
use App\Actions\Public\Content\ShowPublicPageAction;
use App\Http\Controllers\Controller;
use App\Support\Content\PageRedirectResolver;
use App\Support\Content\PublicSeoBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /** قائمة الصفحات المنشورة (تنقّل/خريطة) — مرشّح placement اختياري (header|footer). */
    public function index(Request $request, string $locale): JsonResponse
    {
        return (new ListPublicPagesAction)->handle($locale, $request);
    }

    /** تفاصيل صفحة منشورة بالـ (locale + slug). */
    public function show(string $locale, string $slug): JsonResponse
    {
        return (new ShowPublicPageAction)->handle($locale, $slug);
    }

    /**
     * مُحلِّل إعادة توجيه 301 (SEO): مسار قانوني قديم كامل (?path=) → الـ canonical
     * الحالي. يستخدمه catch-all الواجهة/الزواحف للروابط القديمة. لا تطابق ⇒ 404.
     */
    public function redirect(Request $request, string $locale): JsonResponse
    {
        $path = (string) $request->query('path', '');
        $target = $path !== '' ? PageRedirectResolver::resolveByPath($locale, $path) : null;

        if ($target === null) {
            return response()->json(['message' => __('page.not_found')], 404);
        }

        $location = PublicSeoBuilder::absoluteUrl($target->canonicalPath());

        return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
    }
}
