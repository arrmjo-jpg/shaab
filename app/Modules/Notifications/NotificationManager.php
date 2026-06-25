<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Dispatch\CampaignDispatcher;
use App\Modules\Notifications\Dispatch\DirectDispatcher;
use App\Modules\Notifications\Enums\DispatchMode;
use App\Modules\Notifications\Enums\Priority;
use App\Modules\Notifications\Events\NotificationEvent;
use App\Modules\Notifications\Models\NotificationEventLog;
use App\Modules\Notifications\Support\EventCatalog;
use App\Modules\Notifications\Support\EventFingerprint;
use App\Modules\Notifications\Support\KillSwitch;
use App\Modules\Notifications\Support\PolicyRouter;
use Illuminate\Support\Facades\Log;

/**
 * العقل المركزيّ — **نقطة الدخول الوحيدة** للنظام. كلّ إشعار (domain/scheduled/manual/system)
 * يمرّ من handle(). يُمنع منعاً باتّاً استدعاء أيّ Driver/Campaign/Laravel-Notification مباشرة
 * من خارجه. التسلسل: Kill Switch (خطوة صفر) → PolicyRouter → Campaign | Direct | Ignore.
 */
final class NotificationManager
{
    public function __construct(
        private readonly KillSwitch $killSwitch,
        private readonly PolicyRouter $router,
        private readonly CampaignDispatcher $campaign,
        private readonly DirectDispatcher $direct,
    ) {}

    public function handle(NotificationEvent $event): void
    {
        $def = EventCatalog::get($event->eventKey);
        $priority = $def['priority'] ?? Priority::Normal;

        // خطوة صفر: Kill Switch — داخل المدير لا الدرايفرات (لا حملة/Job/Direct عند الإيقاف).
        if (! $this->killSwitch->allows($event->source, $priority)) {
            $this->record($event, 'ignore', 'kill switch active');

            return;
        }

        $decision = $this->router->decide($event);
        $this->record($event, $decision->label(), $decision->reason);

        if ($decision->ignored) {
            return;
        }

        match ($decision->dispatch) {
            DispatchMode::Campaign => $this->campaign->dispatch($event, $decision),
            DispatchMode::Direct => $this->direct->dispatch($event, $decision),
        };
    }

    private function record(NotificationEvent $event, string $decision, string $reason): void
    {
        NotificationEventLog::query()->create([
            'event_key' => $event->eventKey,
            'source' => $event->source->value,
            'fingerprint' => EventFingerprint::for($event),
            'payload' => $event->payload,
            'decision' => $decision,
            'occurred_at' => $event->occurredAt ?? now(),
        ]);

        if ($decision === 'ignore') {
            Log::info('notifications.event.ignored', ['event' => $event->eventKey, 'reason' => $reason]);
        }
    }
}
