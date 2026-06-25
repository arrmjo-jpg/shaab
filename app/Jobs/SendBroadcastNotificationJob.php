<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Support\Broadcast\BroadcastPresenceControl;
use App\Support\Broadcast\BroadcastPushGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * نشر إشعار بثّ (live|reminder) إلى موضوع التسليم (FCM topic) — رسالة واحدة للموضوع،
 * لا حلقة على المستخدمين (التوزيع مسؤولية المزوّد). يحمل المُعرّف فقط (لا نموذج
 * مُسلسَل) فيُعاد تحميله طازجاً عند التنفيذ.
 *
 * آمن لإعادة المحاولة:
 *   1) يُعيد التحقّق من الحالة عند التنفيذ (مباشر فعلاً / مجدول فعلاً + عام) ⇒ يتعامل
 *      مع التراجع/الإلغاء/الأرشفة/تغيّر الجدولة (لا نشر لحالةٍ فائتة).
 *   2) مطالبة نشرٍ ذرّية (Cache::add) ⇒ إعادة محاولة الطابور لا تُكرّر النشر. مفتاح
 *      التذكير يحمل موعد الجدولة فإعادة الجدولة تسمح بنشرٍ جديد بينما تمنع التكرار.
 */
class SendBroadcastNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $type,    // 'live' | 'reminder'
        public readonly int $broadcastId,
    ) {}

    public function handle(BroadcastPushGateway $gateway): void
    {
        $broadcast = Broadcast::query()->whereKey($this->broadcastId)->first();
        if ($broadcast === null || ! $this->stillValid($broadcast)) {
            return; // فائت/ملغى/غير عام — لا نشر (أمان إعادة المحاولة/التراجع)
        }

        // منع تكرار النشر تحت إعادة المحاولة (ذرّي): الفائز فقط ينشر.
        if (! Cache::add($this->publishClaimKey($broadcast), true, now()->addHours(6))) {
            return;
        }

        $payload = [
            'type' => $this->type,
            'broadcast_id' => (int) $broadcast->id,
            'kind' => $broadcast->kind->value,
            'title' => $broadcast->title,
            'slug' => $broadcast->slug,
            'canonical_path' => $broadcast->canonicalPath(),
        ];

        $eventTopic = (string) config('broadcast.notifications.topics.event_prefix', 'broadcast.').$broadcast->id;

        if ($this->type === 'live') {
            // العام: كل مشتركي «البثوث المباشرة»؛ + موضوع الحدث (مشتركو التذكير يعلمون البدء).
            $gateway->publish((string) config('broadcast.notifications.topics.live', 'broadcasts-live'), $payload);
            $gateway->publish($eventTopic, $payload);

            return;
        }

        // تذكير: موضوع الحدث فقط (مشتركو هذا البثّ).
        $gateway->publish($eventTopic, $payload);
    }

    private function stillValid(Broadcast $broadcast): bool
    {
        if (! BroadcastPresenceControl::isPubliclyVisible($broadcast->status->value, (bool) $broadcast->is_public)) {
            return false;
        }

        return match ($this->type) {
            'live' => $broadcast->status === BroadcastStatus::Live,
            'reminder' => $broadcast->status === BroadcastStatus::Scheduled,
            default => false,
        };
    }

    /** مفتاح المطالبة بالنشر — التذكير يحمل موعد الجدولة (إعادة الجدولة ⇒ مفتاح جديد). */
    private function publishClaimKey(Broadcast $broadcast): string
    {
        if ($this->type === 'reminder') {
            return "bnotif:pub:reminder:{$this->broadcastId}:".($broadcast->scheduled_at?->getTimestamp() ?? 0);
        }

        return "bnotif:pub:live:{$this->broadcastId}";
    }
}
