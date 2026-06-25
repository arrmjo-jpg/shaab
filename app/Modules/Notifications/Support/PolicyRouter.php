<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\DispatchMode;
use App\Modules\Notifications\Events\NotificationEvent;
use App\Modules\Notifications\Models\NotificationEventType;

/**
 * موجّه السياسة — يقرّر Campaign | Direct | Ignore لحدثٍ ما. (Kill Switch ليس هنا — يُفحَص في
 * NotificationManager قبله.) v1.1: يعتمد الكتالوج + تفعيل الحدث؛ Quiet Hours/التفضيلات/المصفوفة
 * تُضاف في أطوارها. لا يلمس قنوات ولا درايفرات.
 */
final class PolicyRouter
{
    public function decide(NotificationEvent $event): Decision
    {
        $def = EventCatalog::get($event->eventKey);
        if ($def === null) {
            return Decision::ignore('unknown event: '.$event->eventKey);
        }

        $type = NotificationEventType::query()->where('key', $event->eventKey)->first();
        if ($type !== null && ! $type->enabled) {
            return Decision::ignore('event disabled: '.$event->eventKey);
        }

        return $def['dispatch'] === DispatchMode::Direct
            ? Decision::direct($def['priority'])
            : Decision::campaign($def['priority']);
    }
}
