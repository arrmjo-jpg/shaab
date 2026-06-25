<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content;

use App\Actions\Admin\Content\ArticleAnalyticsAction;
use App\Actions\Admin\Content\ArticleEntityAnalyticsAction;
use App\Actions\Admin\Content\ArticleStatsAction;
use App\Actions\Admin\Content\ClearBreakingArticlesAction;
use App\Actions\Admin\Content\ClearPinnedArticlesAction;
use App\Actions\Admin\Content\CreateArticleAction;
use App\Actions\Admin\Content\DeleteArticleAction;
use App\Actions\Admin\Content\DeleteArticleMediaAction;
use App\Actions\Admin\Content\ForceDeleteArticleAction;
use App\Actions\Admin\Content\ListArticleMediaAction;
use App\Actions\Admin\Content\ListArticlesAction;
use App\Actions\Admin\Content\ReorderArticleMediaAction;
use App\Actions\Admin\Content\ResolveEmbedAction;
use App\Actions\Admin\Content\RestoreArticleAction;
use App\Actions\Admin\Content\TransitionArticleStatusAction;
use App\Actions\Admin\Content\UpdateArticleAction;
use App\Actions\Admin\Content\UploadArticleMediaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\ReorderArticleMediaRequest;
use App\Http\Requests\Admin\Content\ResolveEmbedRequest;
use App\Http\Requests\Admin\Content\SlugCheckRequest;
use App\Http\Requests\Admin\Content\StoreArticleRequest;
use App\Http\Requests\Admin\Content\TransitionArticleRequest;
use App\Http\Requests\Admin\Content\UpdateArticleRequest;
use App\Http\Requests\Admin\Content\UploadArticleMediaRequest;
use App\Http\Resources\Admin\Content\ArticleResource;
use App\Http\Resources\Public\Content\PublicArticleResource;
use App\Models\Article;
use App\Support\Content\ArticleSeoGuidance;
use App\Support\Content\SlugGenerator;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ListArticlesAction)->handle();
    }

    /** تحليلات أسطول المقالات (عبر-المقالات): لوحة صدارة + لغة + أثر تمييز + وقت نشر. */
    public function analytics(): JsonResponse
    {
        return (new ArticleAnalyticsAction)->handle();
    }

    /** تحليلات مقال واحد ضمن نطاق زمني (24h/7d/30d/custom): تفاعل + اتجاه + زيارات + أداء. */
    public function entityAnalytics(Request $request, Article $article): JsonResponse
    {
        return (new ArticleEntityAnalyticsAction)->handle(
            $article,
            $request->query('range'),
            $request->query('from'),
            $request->query('to'),
        );
    }

    public function show(Article $article): JsonResponse
    {
        return ApiResponse::success(
            data: new ArticleResource(
                $article->load(['author:id,name,avatar', 'primaryCategory:id,name,slug', 'categories:id,name,slug', 'tags', 'mediaAssets', 'ogImage'])
            )
        );
    }

    /**
     * معاينة حقيقية: حمولة المقال العامة بالضبط (PublicArticleResource + SEO)
     * بصرف النظر عن الحالة (مسودّة/مجدوَل) — يراها المحرّر كما يراها المستخدم.
     * مرفقة بإرشاد SEO تحريري حقيقي.
     */
    public function preview(Article $article): JsonResponse
    {
        $article->load([
            'author:id,name,bio,avatar', 'primaryCategory:id,name,slug',
            'categories:id,name,slug', 'tags', 'mediaAssets', 'ogImage',
        ]);

        return ApiResponse::success(data: [
            'preview' => (new PublicArticleResource($article))->resolve(),
            'seo_guidance' => ArticleSeoGuidance::for($article),
        ]);
    }

    /** فحص توفّر slug + اقتراح بديل (وضوح تعارض الـ slug في المحرّر). */
    public function slugCheck(SlugCheckRequest $request): JsonResponse
    {
        $v = $request->validated();
        $slug = SlugGenerator::makeWithFallback((string) $v['slug']);
        $locale = (string) $v['locale'];
        $ignoreId = isset($v['ignore_id']) ? (int) $v['ignore_id'] : null;

        $taken = fn (string $s): bool => Article::withTrashed()
            ->where('locale', $locale)
            ->where('slug', $s)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if (! $taken($slug)) {
            return ApiResponse::success(data: ['available' => true, 'slug' => $slug, 'suggestion' => $slug]);
        }

        $suggestion = $slug;
        for ($i = 2; $i <= 50; $i++) {
            $candidate = "{$slug}-{$i}";
            if (! $taken($candidate)) {
                $suggestion = $candidate;
                break;
            }
        }

        return ApiResponse::success(data: ['available' => false, 'slug' => $slug, 'suggestion' => $suggestion]);
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        return (new CreateArticleAction)->handle($request->validated(), $request->user());
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        return (new UpdateArticleAction)->handle($article, $request->validated(), $request->user());
    }

    public function status(TransitionArticleRequest $request, Article $article): JsonResponse
    {
        return (new TransitionArticleStatusAction)->handle($article, $request->validated(), $request->user());
    }

    public function destroy(Article $article): JsonResponse
    {
        return (new DeleteArticleAction)->handle($article);
    }

    /** استرجاع مقال محذوف (حذف ناعم). */
    public function restore(Article $article): JsonResponse
    {
        return (new RestoreArticleAction)->handle($article);
    }

    /** حذف نهائي لمقال (لا يمكن استرجاعه). */
    public function forceDelete(Article $article): JsonResponse
    {
        return (new ForceDeleteArticleAction)->handle($article);
    }

    /** يلغي علم «عاجل» عن كل المقالات دفعةً واحدة. */
    public function clearBreaking(): JsonResponse
    {
        return (new ClearBreakingArticlesAction)->handle();
    }

    /** يلغي علم «تثبيت» عن كل المقالات دفعةً واحدة. */
    public function clearPinned(): JsonResponse
    {
        return (new ClearPinnedArticlesAction)->handle();
    }

    /** إحصائات سريعة للأخبار (بطاقات العرض). */
    public function stats(): JsonResponse
    {
        return (new ArticleStatsAction)->handle();
    }

    public function mediaIndex(Article $article): JsonResponse
    {
        return (new ListArticleMediaAction)->handle($article);
    }

    public function uploadMedia(UploadArticleMediaRequest $request, Article $article): JsonResponse
    {
        return (new UploadArticleMediaAction)->handle(
            $article,
            $request->validated('collection'),
            $request->file('file'),
            $request->user(),
        );
    }

    public function reorderMedia(ReorderArticleMediaRequest $request, Article $article): JsonResponse
    {
        return (new ReorderArticleMediaAction)->handle(
            $article,
            $request->validated('collection'),
            $request->validated('ids'),
        );
    }

    public function deleteMedia(Article $article, int $media): JsonResponse
    {
        return (new DeleteArticleMediaAction)->handle($article, $media);
    }

    public function resolveEmbed(ResolveEmbedRequest $request): JsonResponse
    {
        return (new ResolveEmbedAction)->handle($request->validated('url'));
    }
}
