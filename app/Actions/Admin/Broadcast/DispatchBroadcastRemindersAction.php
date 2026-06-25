<?php

declare(strict_types=1);

namespace App\Actions\Admin\Broadcast;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastNotificationJob;
use App\Models\Broadcast;
use Illuminate\Support\Facades\Cache;

/**
 * إرسال تذكيرات البثّ المجدوَل المستحقّة — يجد البثوث المجدولة التي يبدأ موعدها ضمن
 * نافذة التذكير (افتراضياً قبل 30 دقيقة) ولها مشتركون، ويُرسل وظيفة نشرٍ واحدة لكل بثّ
 * (تنشر لموضوع الحدث — لا حلقة على المستخدمين). يُدار عبر SchedulerRegistry everyMinute.
 *
 * منع تكرار: علامة reminder_dispatched_at تُضبط ذرّياً (الفائز يُرسل) + قفل موزّع يمنع
 * التداخل. idempotent وآمن لإعادة التشغيل. تغيّر الجدولة يُصفّر العلامة (في المنسّق) فيُعاد
 * الإرسال في الموعد الجديد؛ والبثّ الملغى/المؤرشف لا يُطابِق (status=scheduled فقط).
 *
 * @return int عدد البثوث التي أُرسل لها تذكير
 */
final class DispatchBroadcastRemindersAction
{
    private const LOCK_KEY = 'broadcasts:dispatch-reminders';

    public function handle(): int
    {
        if (! (bool) config('broadcast.notifications.enabled', true)) {
            return 0;
        }

        $lock = Cache::lock(self::LOCK_KEY, 110);
        if (! $lock->get()) {
            return 0; // تشغيل آخر جارٍ — تخطٍّ آمن
        }

        $dispatched = 0;

        try {
            $lead = max(1, (int) config('broadcast.notifications.reminder_lead_minutes', 30));
            $windowEnd = now()->addMinutes($lead);

            Broadcast::query()
                ->where('status', BroadcastStatus::Scheduled->value)
                ->whereNull('reminder_dispatched_at')
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '>', now())        // لم يبدأ بعد
                ->where('scheduled_at', '<=', $windowEnd)   // دخل نافذة التذكير
                ->whereExists(function ($query): void {     // له مشتركو تذكير (تفادي نشرٍ فارغ)
                    $query->selectRaw('1')
                        ->from('broadcast_notification_subscriptions')
                        ->whereColumn('broadcast_notification_subscriptions.broadcast_id', 'broadcasts.id');
                })
                ->orderBy('id')
                ->chunkById(100, function ($chunk) use (&$dispatched): void {
                    foreach ($chunk as $broadcast) {
                        // مطالبة ذرّية بالعلامة — الفائز فقط يُرسل (منع تكرار/تداخل).
                        $claimed = Broadcast::query()
                            ->whereKey($broadcast->id)
                            ->whereNull('reminder_dispatched_at')
                            ->update(['reminder_dispatched_at' => now()]);

                        if ($claimed !== 1) {
                            continue;
                        }

                        SendBroadcastNotificationJob::dispatch('reminder', (int) $broadcast->id);
                        $dispatched++;
                    }
                });
        } finally {
            $lock->release();
        }

        return $dispatched;
    }
}
