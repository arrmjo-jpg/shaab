<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // SportMonks Football API
        $this->migrator->add('third_party.sportmonks_enabled', false);
        $this->migrator->addEncrypted('third_party.sportmonks_api_key', '');
        $this->migrator->add('third_party.sportmonks_base_url', 'https://api.sportmonks.com/v3');

        // OpenWeather API
        $this->migrator->add('third_party.openweather_enabled', false);
        $this->migrator->addEncrypted('third_party.openweather_api_key', '');
        $this->migrator->add('third_party.openweather_base_url', 'https://api.openweathermap.org/data/3.0');
        $this->migrator->add('third_party.openweather_units', 'metric');
        $this->migrator->add('third_party.openweather_default_language', 'ar');
    }
};
