<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Google Gemini Text-to-Speech — تفعيل ميزة «الاستماع للمقال» + مفتاح الـAPI (مشفّر).
        $this->migrator->add('third_party.gemini_tts_enabled', false);
        $this->migrator->addEncrypted('third_party.gemini_tts_api_key', '');
    }
};
