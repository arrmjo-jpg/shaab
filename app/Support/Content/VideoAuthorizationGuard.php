<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\User;
use App\Models\Video;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حارس تفويض الفيديو + إسناد الكاتب (قرارات العمل المقفولة).
 *
 * تحريري = super_admin أو editor (إنشاء/تعديل أي فيديو).
 * كاتب    = is_writer وغير تحريري:
 *   - يُنشئ فيديو باسمه فقط (author يُربط ذاتياً، يُرفض أيّ author مُرسَل)
 *   - يعدّل مسوّداته/مرفوضاته فقط، ولا يعدّل ما لا يملك
 *
 * نمط مطابق لـ ReelAuthorizationGuard — لا Policy (اتساق معماري معتمد).
 */
final class VideoAuthorizationGuard
{
    private const EDITORIAL_ROLES = ['super_admin', 'editor'];

    public static function isEditorial(User $user): bool
    {
        return $user->hasAnyRole(self::EDITORIAL_ROLES);
    }

    public static function forCreate(User $actor, ?int $requestedAuthorId): ?JsonResponse
    {
        $editorial = self::isEditorial($actor);

        if (! $editorial && ! $actor->is_writer) {
            return ApiResponse::error(__('video.cannot_create'), [], 403);
        }

        // كاتب (غير تحريري): يُنشئ باسمه فقط — لا تمرير author_id
        if (! $editorial && $requestedAuthorId !== null) {
            return ApiResponse::error(__('video.writer_author_forbidden'), [], 422);
        }

        // تحريري: إن مُرِّر author يجب أن يوجد
        if ($editorial && $requestedAuthorId !== null
            && ! User::query()->whereKey($requestedAuthorId)->exists()) {
            return ApiResponse::error(__('video.author_not_found'), [], 422);
        }

        return null;
    }

    public static function forUpdate(User $actor, Video $video): ?JsonResponse
    {
        if (self::isEditorial($actor)) {
            return null;
        }

        // كاتب: يملك الفيديو فقط
        if ($video->author_id !== $actor->id) {
            return ApiResponse::error(__('video.writer_cannot_edit_others'), [], 403);
        }

        return null;
    }

    /**
     * الكاتب الفعّال بعد اجتياز الحارس (ربط ذاتي للكاتب، مختار للتحريري).
     */
    public static function resolveAuthorId(User $actor, ?int $requestedAuthorId): int
    {
        if (! self::isEditorial($actor)) {
            return $actor->id; // ربط ذاتي للكاتب
        }

        return $requestedAuthorId ?? $actor->id;
    }
}
