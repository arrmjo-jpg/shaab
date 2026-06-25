<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\ReelStatus;
use App\Models\Reel;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * حارس انتقالات حالة الريل (سير عمل النشر — قرارات مقفولة).
 *
 * الحالات: draft → submitted → in_review → scheduled → published،
 *           إضافة rejected و archived. نمط مطابق لـ ArticleWorkflowGuard.
 *
 * صلاحيات الانتقال:
 *  - الكاتب (غير تحريري، يملك الريل): submit/resubmit فقط
 *    (draft|rejected → submitted). لا نشر/جدولة/رفض/أرشفة إطلاقاً.
 *  - التحريري (super_admin|editor): كل الانتقالات ضمن المصفوفة، مع فرض
 *    صلاحية دقيقة للنشر (reels.publish) والأرشفة (reels.archive) — يُبقي
 *    البوّابة الدقيقة القائمة في TransitionReelStatusAction.
 *
 * محتوى الكاتب لا يُنشَر تلقائياً أبداً. لا Policy (اتساق معماري معتمد).
 */
final class ReelWorkflowGuard
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
        Reel $reel,
        ReelStatus $target,
        ?Carbon $scheduledAt
    ): ?JsonResponse {
        $from = $reel->status->value;
        $to = $target->value;

        if ($from === $to) {
            return ApiResponse::error(__('reel.invalid_transition'), [], 422);
        }

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return ApiResponse::error(__('reel.invalid_transition'), [], 422);
        }

        $editorial = ReelAuthorizationGuard::isEditorial($actor);

        if (! $editorial) {
            // كاتب: يملك الريل فقط
            if ($reel->author_id !== $actor->id) {
                return ApiResponse::error(__('reel.writer_cannot_edit_others'), [], 403);
            }

            // كاتب: submit/resubmit فقط
            if (! in_array($to, self::WRITER_ALLOWED[$from] ?? [], true)) {
                return ApiResponse::error(__('reel.writer_transition_forbidden'), [], 403);
            }
        } else {
            // تحريري: النشر/الأرشفة يتطلّبان صلاحية دقيقة (البوّابة القائمة).
            $ability = match ($target) {
                ReelStatus::Published => 'reels.publish',
                ReelStatus::Archived => 'reels.archive',
                default => null,
            };

            if ($ability !== null && ! $actor->can($ability)) {
                return ApiResponse::error(__('reel.forbidden_transition'), [], 403);
            }
        }

        // الجدولة تتطلّب تاريخاً مستقبلياً
        if ($target === ReelStatus::Scheduled) {
            if ($scheduledAt === null || $scheduledAt->isPast()) {
                return ApiResponse::error(__('reel.schedule_future'), [], 422);
            }
        }

        return null;
    }
}
