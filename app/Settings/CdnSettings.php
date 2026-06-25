<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * إعدادات CDN — Cloudflare فقط.
 */
class CdnSettings extends Settings
{
    public bool $cdn_enabled;

    public bool $cdn_auto_purge;

    public string $cdn_plan;

    public string $cdn_api_token;

    public string $cdn_zone_id;

    public static function group(): string
    {
        return 'cdn';
    }

    public static function encrypted(): array
    {
        return ['cdn_api_token'];
    }
}
