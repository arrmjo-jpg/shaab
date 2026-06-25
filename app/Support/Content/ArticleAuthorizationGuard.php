<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Article;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حارس تفويض المقال + إسناد الكاتب (قرارات العمل المقفولة).
 *
 * تحريري = super_admin أو editor (إنشاء/تعديل أي شيء، يشمل live).
 * كاتب    = is_writer وغير تحريري:
 *   - يُنشئ news/opinion فقط (لا live)
 *   - author يُربط ذاتياً (يُتجاهل أي author مُرسَل)
 *   - يعدّل مسوّداته/مرفوضاته فقط، ولا يعدّل ما لا يملك
 * إسناد كاتب الرأي: عند الإنشاء التحريري لمقال رأي، يجب اختيار مستخدم
 * is_writer=true.
 *
 * نمط مطابق لـ RoleEscalationGuard — لا Policy (اتساق معماري معتمد).
 */
final class ArticleAuthorizationGuard
{
    private const EDITORIAL_ROLES = ['super_admin', 'editor'];

    public static function isEditorial(User $user): bool
    {
        return $user->hasAnyRole(self::EDITORIAL_ROLES);
    }

    public static function forCreate(User $actor, ArticleType $type, ?int $requestedAuthorId): ?JsonResponse
    {
        $editorial = self::isEditorial($actor);

        if (! $editorial && ! $actor->is_writer) {
            return ApiResponse::error(__('article.cannot_create'), [], 403);
        }

        // كاتب (غير تحريري)
        if (! $editorial) {
            // قاعدة صريحة مقفولة: الكاتب يُنشئ باسمه فقط — لا تمرير author_id
            if ($requestedAuthorId !== null) {
                return ApiResponse::error(__('article.writer_author_forbidden'), [], 422);
            }

            if ($type === ArticleType::Live) {
                return ApiResponse::error(__('article.writer_cannot_create_live'), [], 403);
            }

            return null; // author يُربط ذاتياً
        }

        // تحريري ينشئ رأياً ⇒ يجب اختيار كاتب is_writer
        if ($type === ArticleType::Opinion) {
            return self::validateOpinionAuthor($requestedAuthorId);
        }

        // news/live: إن مُرِّر author يجب أن يوجد
        if ($requestedAuthorId !== null && ! User::query()->whereKey($requestedAuthorId)->exists()) {
            return ApiResponse::error(__('article.author_not_found'), [], 422);
        }

        return null;
    }

    public static function forUpdate(User $actor, Article $article, ?ArticleType $newType): ?JsonResponse
    {
        if (self::isEditorial($actor)) {
            return null;
        }

        // كاتب: يملك المقال فقط
        if ($article->author_id !== $actor->id) {
            return ApiResponse::error(__('article.writer_cannot_edit_others'), [], 403);
        }

        // كاتب: حالة قابلة للتعديل فقط (مسودّة/مرفوض)
        if (! in_array($article->status, [ArticleStatus::Draft, ArticleStatus::Rejected], true)) {
            return ApiResponse::error(__('article.writer_edit_state_locked'), [], 403);
        }

        // كاتب لا يحوّل النوع إلى live
        if ($newType === ArticleType::Live) {
            return ApiResponse::error(__('article.writer_cannot_create_live'), [], 403);
        }

        return null;
    }

    /**
     * الكاتب الفعّال بعد اجتياز الحارس (ربط ذاتي للكاتب، مختار للتحريري).
     */
    public static function resolveAuthorId(User $actor, ArticleType $type, ?int $requestedAuthorId): int
    {
        if (! self::isEditorial($actor)) {
            return $actor->id; // ربط ذاتي للكاتب
        }

        if ($type === ArticleType::Opinion) {
            return (int) $requestedAuthorId; // مُتحقَّق منه في forCreate
        }

        return $requestedAuthorId ?? $actor->id;
    }

    private static function validateOpinionAuthor(?int $authorId): ?JsonResponse
    {
        if ($authorId === null) {
            return ApiResponse::error(__('article.opinion_author_must_be_writer'), [], 422);
        }

        $author = User::query()->whereKey($authorId)->first();
        if ($author === null) {
            return ApiResponse::error(__('article.author_not_found'), [], 422);
        }

        if (! $author->is_writer) {
            return ApiResponse::error(__('article.opinion_author_must_be_writer'), [], 422);
        }

        return null;
    }
}
