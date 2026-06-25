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
 * يزيل علم «تثبيت» عن كل المقالات دفعةً واحدة. يمرّ عبر Eloquent (save) ليبقى
 * التغيير مُدقَّقاً (is_pinned ضمن auditAttributes) — عدد المثبَّت عادةً صغير،
 * والعمود مفهرس فالجلب نقطيّ سريع (لا مسح كامل).
 */
class ClearPinnedArticlesAction
{
    public function handle(): JsonResponse
    {
        $articles = Article::query()->where('is_pinned', true)->get();

        foreach ($articles as $article) {
            $article->is_pinned = false;
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
            __('article.pinned_cleared', ['count' => $articles->count()]),
            ['cleared' => $articles->count()],
        );
    }
}
