<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Public\Content\ListBreakingArticlesAction;
use App\Actions\Public\Content\ListMostReadArticlesAction;
use App\Actions\Public\Content\ListPublicArticlesAction;
use App\Actions\Public\Content\ListTrendingArticlesAction;
use App\Actions\Public\Content\ShowPublicArticleAction;
use App\Http\Controllers\Controller;
use App\Support\Content\ArticleRedirectResolver;
use App\Support\Content\PublicSeoBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request, string $locale): JsonResponse
    {
        return (new ListPublicArticlesAction)->handle($locale, $request);
    }

    public function show(string $locale, string $slug): JsonResponse
    {
        return (new ShowPublicArticleAction)->handle($locale, $slug);
    }

    /** المسار السريع للأخبار العاجلة (ticker خفيف, TTL قصير). */
    public function breaking(string $locale): JsonResponse
    {
        return (new ListBreakingArticlesAction)->handle($locale);
    }

    /** الأكثر قراءة (تحليلات: مشاهدات مُتتبَّعة). */
    public function mostRead(Request $request, string $locale): JsonResponse
    {
        return (new ListMostReadArticlesAction)->handle($locale, $request);
    }

    /** الرائج الآن (تحليلات: تفاعل موزون ضمن نافذة حديثة). */
    public function trending(Request $request, string $locale): JsonResponse
    {
        return (new ListTrendingArticlesAction)->handle($locale, $request);
    }

    /**
     * مُحلِّل إعادة التوجيه 301 (SEO/هجرة): يستقبل مساراً قانونياً قديماً كاملاً
     * (?path=/{locale}/articles/{id}-{slug}) ويُعيد 301 إلى الـ canonical الحالي.
     * يستخدمه catch-all الواجهة/الزواحف للروابط القديمة. لا تطابق ⇒ 404.
     */
    public function redirect(Request $request, string $locale): JsonResponse
    {
        $path = (string) $request->query('path', '');

        $target = $path !== ''
            ? ArticleRedirectResolver::resolveByPath($locale, $path)
            : null;

        if ($target === null) {
            return response()->json(['message' => __('article.not_found')], 404);
        }

        $location = PublicSeoBuilder::absoluteUrl($target->canonicalPath());

        return new JsonResponse(['redirect' => $location], 301, ['Location' => $location]);
    }
}
