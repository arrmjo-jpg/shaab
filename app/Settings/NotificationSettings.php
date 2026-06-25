<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * إعدادات مركز الإشعارات. enabled = Kill Switch الرئيسيّ (يوقف إنشاء/تنفيذ الحملات والـDirect)؛
 * critical_bypass يسمح بأحداث system الحرجة عند الإيقاف. Quiet Hours تُقيّم بتوقيت الموقع.
 * (قائمة مقصودة الحدّ الأدنى — لا toggles per-channel هنا؛ مكانها مصفوفة event×channel.)
 */
class NotificationSettings extends Settings
{
    public bool $enabled;

    public bool $critical_bypass;

    public bool $quiet_hours_enabled;

    public string $quiet_hours_start;

    public string $quiet_hours_end;

    public string $quiet_hours_timezone;

    public static function group(): string
    {
        return 'notifications';
    }
}
