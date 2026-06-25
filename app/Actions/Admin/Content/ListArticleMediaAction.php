<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Support\Content\ArticleMediaPresenter;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * يعيد كتل وسائط المقال (cover/gallery/inline/video) — مصدر بيانات مستقلّ
 * لاستوديو الوسائط (P9.1) كي لا تُعيد عمليات الوسائط ضبط نموذج تحرير المقال.
 */
class ListArticleMediaAction
{
    public function handle(Article $article): JsonResponse
    {
        return ApiResponse::success(
            data: ArticleMediaPresenter::admin($article->load('mediaAssets'))
        );
    }
}
