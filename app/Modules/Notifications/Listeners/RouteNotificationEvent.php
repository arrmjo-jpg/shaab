<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Events\NotificationEvent;
use App\Modules\Notifications\NotificationManager;

/**
 * الجسر الوحيد من حدث Laravel إلى العقل المركزيّ — كلّ NotificationEvent مُطلَق يُوجَّه إلى
 * NotificationManager::handle(). لا منطق هنا (توصيل فقط) ⇒ مسار إرسال واحد لا ثانيَ له.
 */
final class RouteNotificationEvent
{
    public function __construct(private readonly NotificationManager $manager) {}

    public function handle(NotificationEvent $event): void
    {
        $this->manager->handle($event);
    }
}
