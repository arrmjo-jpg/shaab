<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * مفتاح الموقع: «هل لهذا الموقع جريدة مطبوعة؟» — يبوّب ظهور وحدات الجريدة الرقمية
 * في الإدارة + الواجهة العامة. معطّل افتراضياً (لا تظهر الوحدة ما لم يُفعَّل صراحةً).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('newspaper.enabled', false);
        $this->migrator->add('newspaper.display_name', 'الجريدة الرقمية');
    }
};
