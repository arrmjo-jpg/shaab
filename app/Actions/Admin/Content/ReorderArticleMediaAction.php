<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Support\Content\ArticleMediaPresenter;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * يعيد ترتيب وسائط مجموعة (عادةً gallery) عبر عمود position في الـ pivot.
 *
 * - يُقبَل فقط معرّفات الأصول المُسنَدة فعلاً للمقال+المجموعة (تجاهل الدخيل).
 * - يُعيد كتل الوسائط المحدَّثة بالترتيب الجديد.
 * - يُفرَّغ كاش القراءة العامة.
 */
class ReorderArticleMediaAction
{
    public function handle(Article $article, string $collection, array $orderedIds): JsonResponse
    {
        // معرّفات الأصول المُسنَدة لهذه المجموعة
        $validIds = $article->mediaAssets()
            ->wherePivot('collection', $collection)
            ->pluck('media_assets.id')
            ->flip(); // [id => index] لبحث O(1)

        $position = 0;
        DB::transaction(function () use ($article, $collection, $orderedIds, $validIds, &$position): void {
            foreach ($orderedIds as $id) {
                if ($validIds->has((int) $id)) {
                    DB::table('article_media')
                        ->where('article_id', $article->id)
                        ->where('media_asset_id', (int) $id)
                        ->where('collection', $collection)
                        ->update(['position' => $position++]);
                }
            }
        });

        Cache::tags(['articles'])->flush();

        return ApiResponse::success(
            __('media.reordered'),
            ArticleMediaPresenter::admin($article->load('mediaAssets'))
        );
    }
}
