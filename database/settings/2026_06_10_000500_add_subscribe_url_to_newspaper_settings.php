<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * رابط صفحة الاشتراك — يُعرَض كـ CTA في صفحة تشويق المشتركين. فارغ افتراضياً (لا زرّ).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('newspaper.subscribe_url', '');
    }
};
