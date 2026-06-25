<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\Priority;

/**
 * أسماء طوابير الوحدة (module-local) — ثلاث درجات أولويّة. العامل يعالجها بالترتيب:
 * `queue:work --queue=notifications-high,notifications,notifications-low`. Critical/High ⇒ HIGH.
 */
final class NotificationQueues
{
    public const HIGH = 'notifications-high';

    public const DEFAULT = 'notifications';

    public const LOW = 'notifications-low';

    public static function forPriority(Priority $priority): string
    {
        return match ($priority) {
            Priority::Critical, Priority::High => self::HIGH,
            Priority::Normal => self::DEFAULT,
            Priority::Low => self::LOW,
        };
    }
}
