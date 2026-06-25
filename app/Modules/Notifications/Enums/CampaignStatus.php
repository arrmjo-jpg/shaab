<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * دورة حياة الحملة الإجماليّة (مشتقّة من حالات قنواتها):
 *   draft → scheduled → queued → sending → {completed | partially_completed | failed}
 *   sending ⇄ paused ·  أيّ غير-طرفيّة → cancelled
 * الاشتقاق: كلّها completed ⇒ completed · ≥1 completed مع skipped/failed ⇒ partially_completed
 *           · لا completed ⇒ failed.  (قناة skipped لا تُسقِط الحملة أبداً.)
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Sending = 'sending';
    case Paused = 'paused';
    case Completed = 'completed';
    case PartiallyCompleted = 'partially_completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::PartiallyCompleted, self::Failed, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * الانتقالات المسموحة من هذه الحالة (حارس آلة الحالة). الإيقاف قبل الإرسال فقط
     * (Scheduled/Queued)؛ لإيقاف حملة جارية استُعمل الإلغاء. الطرفيّة لا تنتقل.
     *
     * @return array<int,self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Queued, self::Cancelled],
            self::Scheduled => [self::Queued, self::Paused, self::Cancelled],
            self::Queued => [self::Sending, self::Paused, self::Cancelled],
            self::Sending => [self::Completed, self::PartiallyCompleted, self::Failed, self::Cancelled],
            self::Paused => [self::Queued, self::Scheduled, self::Cancelled],
            self::Completed, self::PartiallyCompleted, self::Failed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسوّدة',
            self::Scheduled => 'مجدولة',
            self::Queued => 'في الطابور',
            self::Sending => 'قيد الإرسال',
            self::Paused => 'موقوفة',
            self::Completed => 'مكتملة',
            self::PartiallyCompleted => 'مكتملة جزئيًّا',
            self::Failed => 'فاشلة',
            self::Cancelled => 'ملغاة',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s): string => $s->value, self::cases());
    }
}
