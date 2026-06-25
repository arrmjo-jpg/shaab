<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastNotificationJob;
use App\Models\Broadcast;

/**
 * منسّق إشعارات البثّ (B8) — منطق الإطلاق المركزيّ (مصدر واحد، لا مسارات إطلاق مكرّرة).
 *
 * مانع الارتعاش (حرِج): علامة live_notified_at تُضبط ذرّياً، فيُرسَل إشعار «بدأ المباشر»
 * مرّة واحدة فقط لكل بثّ مهما تكرّر الدخول/الخروج (live↔failed↔live بسبب تذبذب الصحّة)
 * — لا قصف للمستخدمين. يُستدعى من مراقب النموذج عند الانتقال إلى live (يغطّي كل مسارات
 * البدء: يدويّ/مجدوَل/استئناف/استرجاع صحّي) دون تكرار.
 */
final class BroadcastNotifier
{
    /** يُطلق إشعار البدء المباشر مرّة واحدة (الفائز بمطالبة العلامة فقط يُرسل). */
    public static function dispatchLiveIfNeeded(Broadcast $broadcast): void
    {
        if (! (bool) config('broadcast.notifications.enabled', true)) {
            return;
        }

        // أهليّة: مباشر فعلاً + عام (لا إشعار لبثّ غير عام/مسودة/مؤرشف).
        if ($broadcast->status !== BroadcastStatus::Live
            || ! BroadcastPresenceControl::isPubliclyVisible($broadcast->status->value, (bool) $broadcast->is_public)) {
            return;
        }

        // مطالبة ذرّية بالعلامة — الفائز فقط يُرسل (مانع ارتعاش + أمان تزامن + منع تكرار
        // الإرسال). update عبر باني الاستعلام: لا أحداث (لا تكرار) ولا طوابع زمنية.
        $claimed = Broadcast::query()
            ->whereKey($broadcast->id)
            ->whereNull('live_notified_at')
            ->update(['live_notified_at' => now()]);

        if ($claimed !== 1) {
            return; // سبق الإشعار (ارتعاش/إعادة/سباق) — لا تكرار
        }

        SendBroadcastNotificationJob::dispatch('live', (int) $broadcast->id);
    }

    /**
     * يصفّر علامة التذكير عند تغيّر موعد الجدولة — كي يُعيد أمرُ التذكير الإرسال في
     * الموعد الجديد (معالجة صحيحة لتغيّر الجدولة).
     */
    public static function resetReminderMarker(Broadcast $broadcast): void
    {
        if ($broadcast->reminder_dispatched_at === null) {
            return;
        }

        Broadcast::query()->whereKey($broadcast->id)->update(['reminder_dispatched_at' => null]);
    }
}
