<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * إعدادات التخزين الهجين — مصدر الحقيقة لتفعيل المرآة البعيدة واعتمادياتها
 * (قابلة للضبط من لوحة الإدارة، لا اعتماد على env للتبديل التشغيلي).
 *
 * تُبذَر القيم من env الحالية لمرّة واحدة كي تطابق إعداد R2 العامل؛ بعدها
 * تُدار من الإعدادات. الحقول الحسّاسة (المفتاح/السرّ) مشفّرة.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('media.remote_enabled', (bool) env('MEDIA_REMOTE_ENABLED', false));
        $this->migrator->add('media.remote_driver', 's3');
        $this->migrator->addEncrypted('media.remote_key', (string) env('AWS_ACCESS_KEY_ID', ''));
        $this->migrator->addEncrypted('media.remote_secret', (string) env('AWS_SECRET_ACCESS_KEY', ''));
        $this->migrator->add('media.remote_bucket', (string) env('AWS_BUCKET', ''));
        $this->migrator->add('media.remote_region', (string) env('AWS_DEFAULT_REGION', 'auto'));
        $this->migrator->add('media.remote_endpoint', (string) env('AWS_ENDPOINT', ''));
        $this->migrator->add('media.remote_url', (string) env('MEDIA_URL', ''));
        $this->migrator->add('media.remote_use_path_style', (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', true));
    }
};
