<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // وصف الموقع — يُستخدم في SEO للواجهة العامّة (meta/og description الافتراضيّ).
        $this->migrator->add('general.site_description', '');
    }
};
