<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * حارس انتقالات حالة الفيديو (سير عمل النشر — قرارات مقفولة).
 *
 * الحالات: draft → submitted → in_review → scheduled → published،
 *           إضافة rejected و archived. نمط مطابق لـ ReelWorkflowGuard.
 *
 * صلاحيات الانتقال:
 *  - الكاتب (غير تحريري، يملك الفيديو): submit/resubmit فقط
 *    (draft|rejected → submitted). لا نشر/جدولة/رفض/أرشفة إطلاقاً.
 *  - التحريري (super_admin|editor): كل الانتقالات ضمن المصفوفة، مع فرض
 *    صلاحية دقيقة للنشر/الجدولة (videos.publish) والأرشفة (videos.archive) —
 *    يُبقي البوّابة الدقيقة القائمة في TransitionVideoStatusAction.
 *
 * محتوى الكاتب لا يُنشَر تلقائياً أبداً. لا Policy (اتساق معماري معتمد).
 */
final class VideoWorkflowGuard
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
        Video $video,
        VideoStatus $target,
        ?Carbon $scheduledAt
    ): ?JsonResponse {
        $from = $video->status->value;
        $to = $target->value;

        if ($from === $to) {
            return ApiResponse::error(__('video.invalid_transition'), [], 422);
        }

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return ApiResponse::error(__('video.invalid_transition'), [], 422);
        }

        $editorial = VideoAuthorizationGuard::isEditorial($actor);

        if (! $editorial) {
            // كاتب: يملك الفيديو فقط
            if ($video->author_id !== $actor->id) {
                return ApiResponse::error(__('video.writer_cannot_edit_others'), [], 403);
            }

            // كاتب: submit/resubmit فقط
            if (! in_array($to, self::WRITER_ALLOWED[$from] ?? [], true)) {
                return ApiResponse::error(__('video.writer_transition_forbidden'), [], 403);
            }
        } else {
            // تحريري: النشر/الجدولة/الأرشفة تتطلّب صلاحية دقيقة (البوّابة القائمة).
            $ability = match ($target) {
                VideoStatus::Published, VideoStatus::Scheduled => 'videos.publish',
                VideoStatus::Archived => 'videos.archive',
                default => null,
            };

            if ($ability !== null && ! $actor->can($ability)) {
                return ApiResponse::error(__('video.forbidden_transition'), [], 403);
            }
        }

        // الجدولة تتطلّب تاريخاً مستقبلياً
        if ($target === VideoStatus::Scheduled) {
            if ($scheduledAt === null || $scheduledAt->isPast()) {
                return ApiResponse::error(__('video.schedule_requires_date'), [], 422);
            }
        }

        return null;
    }
}
