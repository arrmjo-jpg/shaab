<?php

declare(strict_types=1);

namespace App\Support\Audit;

/**
 * تسجيل تدقيق يدوي لتغييرات الإعدادات (Spatie Settings ليست Eloquent
 * فلا يلتقطها التدقيق التلقائي). يُسجَّل أسماء المفاتيح فقط — بلا قيم
 * (تجنّب تسريب الأسرار).
 *
 * @param  array<int,string>  $changedKeys
 * @param  array<int,string>  $secretKeys
 */
final class SettingsAudit
{
    public static function log(string $group, array $changedKeys, array $secretKeys = []): void
    {
        $keys = array_values(array_diff($changedKeys, $secretKeys));

        if ($keys === []) {
            return;
        }

        activity('settings')
            ->event('updated')
            ->withProperties(['group' => $group, 'changed' => $keys])
            ->log(__('audit.settings_updated', ['group' => $group]));
    }
}
