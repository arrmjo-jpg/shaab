<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * يُزيل إسناد أصل من مقال (يُزيل صف pivot فقط — الأصل يبقى في المكتبة).
 */
class DeleteArticleMediaAction
{
    public function handle(Article $article, int $mediaAssetId): JsonResponse
    {
        $exists = $article->mediaAssets()
            ->where('media_assets.id', $mediaAssetId)
            ->exists();

        if (! $exists) {
            return ApiResponse::error(__('media.not_found'), [], 404);
        }

        $article->mediaAssets()->detach($mediaAssetId);

        return ApiResponse::success(__('media.deleted'));
    }
}
