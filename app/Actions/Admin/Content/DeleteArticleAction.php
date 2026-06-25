<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Content\ArticleCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeleteArticleAction
{
    public function handle(Article $article): JsonResponse
    {
        // إبطال حبيبي قبل الحذف الناعم (العلاقات لا تزال متاحة لاشتقاق وسوم التصنيف).
        $tags = ArticleCacheTags::writeTags($article);

        $article->delete(); // soft delete (revisions/url-history preserved)

        Cache::tags($tags)->flush();
        ArticleCdnPurge::purge($article);

        return ApiResponse::success(__('article.deleted'));
    }
}
