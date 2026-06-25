<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('cdn.cdn_enabled', false);
        $this->migrator->add('cdn.cdn_auto_purge', false);
        $this->migrator->add('cdn.cdn_plan', 'free');
        $this->migrator->addEncrypted('cdn.cdn_api_token', '');
        $this->migrator->add('cdn.cdn_zone_id', '');
    }
};
