<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Dispatch;

use App\Modules\Notifications\Events\NotificationEvent;
use App\Modules\Notifications\Support\Decision;
use Illuminate\Support\Facades\Log;

/**
 * موزّع الإشعارات المباشرة — يُستدعى من NotificationManager وحده. Phase 4: يحلّ المستخدم من
 * الحمولة ويستدعي Laravel Notification (via database/mail — جدول notifications القائم). الآن: يسجّل.
 */
final class DirectDispatcher
{
    public function dispatch(NotificationEvent $event, Decision $decision): void
    {
        Log::info('notifications.direct.dispatch', [
            'event' => $event->eventKey,
        ]);
        // TODO(Phase 4): حلّ المستخدم المستهدف من الحمولة → $user->notify(...) عبر قنوات Laravel.
    }
}
