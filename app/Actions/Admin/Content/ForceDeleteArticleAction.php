<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Content\ArticleCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ForceDeleteArticleAction
{
    public function handle(Article $article): JsonResponse
    {
        // التقط الوسوم/الروابط قبل الحذف النهائي (تُفقَد علاقات التصنيف بعده).
        $tags = ArticleCacheTags::writeTags($article);
        ArticleCdnPurge::purge($article);

        $article->forceDelete(); // حذف نهائي — تتالي مفاتيح الأجنبية ينظّف التبعيات

        Cache::tags($tags)->flush();

        return ApiResponse::success(__('article.force_deleted'));
    }
}
