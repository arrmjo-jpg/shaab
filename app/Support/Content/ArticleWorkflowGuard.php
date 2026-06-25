<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * حارس انتقالات حالة المقال (سير عمل النشر — قرارات مقفولة).
 *
 * الحالات: draft → submitted → in_review → scheduled → published،
 *           إضافة rejected و archived.
 *
 * صلاحيات الانتقال:
 *  - الكاتب (غير تحريري، يملك المقال): submit/resubmit فقط
 *    (draft|rejected → submitted). لا نشر/جدولة/رفض/أرشفة إطلاقاً.
 *  - التحريري (super_admin|editor): كل الانتقالات ضمن المصفوفة.
 *
 * محتوى الكاتب لا يُنشَر تلقائياً أبداً.
 * نمط مطابق لـ ArticleAuthorizationGuard — لا Policy.
 */
final class ArticleWorkflowGuard
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['submitted', 'in_review', 'scheduled', 'published', 'archived'],
        'submitted' => ['in_review', 'scheduled', 'published', 'rejected'],
        'in_review' => ['scheduled', 'published', 'rejected', 'draft'],
        'scheduled' => ['published', 'draft', 'archived'],
        'published' => ['archived'],
        'rejected' => ['draft', 'submitted'],
        'archived' => ['draft'],
    ];

    /** الانتقالات المسموحة للكاتب فقط (from → to). */
    private const WRITER_ALLOWED = [
        'draft' => ['submitted'],
        'rejected' => ['submitted'],
    ];

    public static function check(
        User $actor,
        Article $article,
        ArticleStatus $target,
        ?Carbon $scheduledAt
    ): ?JsonResponse {
        $from = $article->status->value;
        $to = $target->value;

        if ($from === $to) {
            return ApiResponse::error(__('article.invalid_transition'), [], 422);
        }

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return ApiResponse::error(__('article.invalid_transition'), [], 422);
        }

        $editorial = ArticleAuthorizationGuard::isEditorial($actor);

        if (! $editorial) {
            // كاتب: يملك المقال فقط
            if ($article->author_id !== $actor->id) {
                return ApiResponse::error(__('article.writer_cannot_edit_others'), [], 403);
            }

            // كاتب: submit/resubmit فقط
            if (! in_array($to, self::WRITER_ALLOWED[$from] ?? [], true)) {
                return ApiResponse::error(__('article.writer_transition_forbidden'), [], 403);
            }
        }

        // الجدولة تتطلّب تاريخاً مستقبلياً
        if ($target === ArticleStatus::Scheduled) {
            if ($scheduledAt === null || $scheduledAt->isPast()) {
                return ApiResponse::error(__('article.schedule_requires_future_date'), [], 422);
            }
        }

        return null;
    }
}
