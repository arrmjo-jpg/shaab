<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حارس تحديثات التغطية الحيّة (P8 — قرار مقفول: نموذج تعاوني تحريري).
 *
 *  - المقال يجب أن يكون type=live (لا تحديثات حيّة لأنواع أخرى).
 *  - أي مستخدم تحريري (super_admin|editor) يضيف/يعدّل/يحذف على أي تغطية حيّة.
 *  - الكتّاب مستبعَدون كلياً من إدارة الخط الزمني الحيّ (قرار مقفول).
 *
 * نمط مطابق لـ ArticleWorkflowGuard — لا Policy.
 */
final class LiveUpdateGuard
{
    public static function authorize(User $actor, Article $article): ?JsonResponse
    {
        if ($article->type !== ArticleType::Live) {
            return ApiResponse::error(__('live_update.not_live_article'), [], 422);
        }

        if (! ArticleAuthorizationGuard::isEditorial($actor)) {
            return ApiResponse::error(__('live_update.editorial_only'), [], 403);
        }

        return null;
    }
}
