<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Content\ArticleCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * يزيل علم «عاجل» عن كل المقالات دفعةً واحدة. يمرّ عبر Eloquent (save) ليبقى
 * التغيير مُدقَّقاً (is_breaking ضمن auditAttributes) — عدد العاجل عادةً صغير.
 */
class ClearBreakingArticlesAction
{
    public function handle(): JsonResponse
    {
        $articles = Article::query()->where('is_breaking', true)->get();

        foreach ($articles as $article) {
            $article->is_breaking = false;
            $article->save();
        }

        $tags = collect($articles)
            ->flatMap(fn (Article $a): array => ArticleCacheTags::writeTags($a))
            ->unique()->values()->all();
        if ($tags !== []) {
            Cache::tags($tags)->flush();
        }
        foreach ($articles as $article) {
            ArticleCdnPurge::purge($article);
        }

        return ApiResponse::success(
            __('article.breaking_cleared', ['count' => $articles->count()]),
            ['cleared' => $articles->count()],
        );
    }
}
