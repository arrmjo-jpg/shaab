<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * إعدادات التخزين الهجين — مصدر الحقيقة التشغيلي لتفعيل المرآة البعيدة
 * (S3/R2) واعتمادياتها. القرص المحلّي (uploads) هو الـ canonical دائماً؛
 * هذه الإعدادات تتحكّم فقط بالنسخ/التسليم البعيد.
 *
 * تدقيق: تغييرات هذه الإعدادات تُسجَّل عبر SettingsAudit::log('media', ...)
 * داخل Update Action (يُضاف في مرحلة لاحقة) — أسماء المفاتيح فقط، لا أسرار.
 */
class MediaStorageSettings extends Settings
{
    public bool $remote_enabled;

    public string $remote_driver;

    public string $remote_key;

    public string $remote_secret;

    public string $remote_bucket;

    public string $remote_region;

    public string $remote_endpoint;

    public string $remote_url;

    public bool $remote_use_path_style;

    public static function group(): string
    {
        return 'media';
    }

    /** حقول حسّاسة — لا تُكشف ولا تُدقَّق قيمتها أبداً. */
    public static function encrypted(): array
    {
        return ['remote_key', 'remote_secret'];
    }
}
