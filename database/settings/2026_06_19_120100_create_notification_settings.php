<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * إعدادات مركز الإشعارات (group: notifications). enabled مفعّل افتراضيّاً (المركز يعمل،
 * والمفتاح للإيقاف الطارئ)؛ critical_bypass مفعّل (تنبيهات النظام الحرجة تخترق الإيقاف)؛
 * Quiet Hours معطّلة افتراضيّاً بتوقيت عمّان.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.enabled', true);
        $this->migrator->add('notifications.critical_bypass', true);
        $this->migrator->add('notifications.quiet_hours_enabled', false);
        $this->migrator->add('notifications.quiet_hours_start', '23:00');
        $this->migrator->add('notifications.quiet_hours_end', '07:00');
        $this->migrator->add('notifications.quiet_hours_timezone', 'Asia/Amman');
    }
};
