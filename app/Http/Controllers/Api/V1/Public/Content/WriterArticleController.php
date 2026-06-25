<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Admin\Content\CreateArticleAction;
use App\Actions\Admin\Content\TransitionArticleStatusAction;
use App\Actions\Public\Content\ListMyArticlesAction;
use App\Actions\Public\Content\ListWriterArticleCategoriesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Content\PublicStoreArticleRequest;
use App\Http\Requests\Public\Content\PublicSubmitArticleRequest;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إرسال مقال من الكاتب (نطاق عام — V1: أخبار/رأي فقط).
 *
 * موصِّل رفيع: يفوّض لـ CreateArticleAction القائم دون أي منطق أعمال.
 * كل التفويض/الإسناد الذاتي/حصر الحالة يُفرَض داخل الـ Action و
 * ArticleAuthorizationGuard (لا يُلمسان). الحقول المحدودة في PublicStoreArticleRequest.
 */
class WriterArticleController extends Controller
{
    public function store(PublicStoreArticleRequest $request): JsonResponse
    {
        return (new CreateArticleAction)->handle(
            $request->validated(),
            $request->user()
        );
    }

    public function submit(PublicSubmitArticleRequest $request, Article $article): JsonResponse
    {
        return (new TransitionArticleStatusAction)->handle(
            $article,
            $request->validated(),
            $request->user()
        );
    }

    public function mine(Request $request): JsonResponse
    {
        return (new ListMyArticlesAction)->handle(
            $request->user(),
            $request
        );
    }

    /** تصنيفات النموذج مفلترةً حسب النوع (news|opinion) — نفس قاعدة نطاق الحارس. */
    public function categories(Request $request): JsonResponse
    {
        return (new ListWriterArticleCategoriesAction)->handle(
            (string) $request->query('type', ''),
            (string) $request->query('locale', 'ar'),
        );
    }
}
