<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Public\Content\ListFeaturedReelsAction;
use App\Actions\Public\Content\ListPublicReelsAction;
use App\Actions\Public\Content\ListTrendingReelsAction;
use App\Actions\Public\Content\ShowPublicReelAction;
use App\Http\Controllers\Controller;
use App\Support\Content\PublicSeoBuilder;
use App\Support\Content\ReelRedirectResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReelController extends Controller
{
    public function index(Request $request, string $locale): JsonResponse
    {
        return (new ListPublicReelsAction)->handle($locale, $request);
    }

    public function featured(Request $request, string $locale): JsonResponse
    {
        return (new ListFeaturedReelsAction)->handle($locale, $request);
    }

    public function trending(Request $request, string $locale): JsonResponse
    {
        return (new ListTrendingReelsAction)->handle($locale, $request);
    }

    public function show(string $locale, string $slug): JsonResponse
    {
        return (new ShowPublicReelAction)->handle($locale, $slug);
    }

    /**
     * مُحلِّل إعادة توجيه 301 (SEO): مسار قانوني قديم كامل (?path=) → الـ canonical
     * الحالي. يستخدمه catch-all الواجهة/الزواحف للروابط القديمة. لا تطابق ⇒ 404.
     */
    public function redirect(Request $request, string $locale): JsonResponse
    {
        $path = (string) $request->query('path', '');
        $target = $path !== '' ? ReelRedirectResolver::resolveByPath($locale, $path) : null;

        if ($target === null) {
            return response()->json(['message' => __('reel.not_found')], 404);
        }

        $location = PublicSeoBuilder::absoluteUrl($target->canonicalPath());

        return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
    }
}
