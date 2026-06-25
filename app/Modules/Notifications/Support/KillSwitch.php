<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\EventSource;
use App\Modules\Notifications\Enums\Priority;
use App\Settings\NotificationSettings;

/**
 * مفتاح الإيقاف الطارئ المركزيّ — يُفحَص خطوةً-صفرًا في Policy. enabled=false يوقف إنشاء/تنفيذ
 * كلّ حملة وكلّ Direct، إلّا (لو critical_bypass) أحداث system الحرجة. يقرأ الإعداد لحظيّاً.
 */
final class KillSwitch
{
    private function settings(): NotificationSettings
    {
        return app(NotificationSettings::class);
    }

    public function isActive(): bool
    {
        return $this->settings()->enabled;
    }

    /** هل يُسمح بهذا الحدث رغم حالة المفتاح؟ */
    public function allows(EventSource $source, Priority $priority): bool
    {
        $settings = $this->settings();

        if ($settings->enabled) {
            return true;
        }

        return $settings->critical_bypass
            && $source === EventSource::System
            && $priority === Priority::Critical;
    }
}
