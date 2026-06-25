<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * إعدادات عامة: معلومات الموقع، البريد، الروابط الاجتماعية،
 * التتبع والتحليلات، أتمتة النسخ الاحتياطي.
 * أسماء الحقول مُنمَّطة بسوابق لتفادي التضارب داخل المجموعة.
 */
class GeneralSettings extends Settings
{
    // ─── Tab 1: معلومات الموقع ──────────────────────────────────────
    public string $site_name;

    public string $site_email;

    public string $site_url;

    public string $timezone;

    public string $site_phone;

    /** وصف الموقع — يُستخدم في SEO للواجهة العامّة (meta/og description الافتراضيّ). */
    public string $site_description;

    public string $copyright_text;

    public string $footer_extra_text;

    public string $cookie_policy_text;

    // العلامة (مسارات ملفات مرفوعة)
    public ?string $logo_light;

    public ?string $logo_dark;

    public ?string $favicon;

    // العلامة المائية
    public bool $watermark_enabled;

    public ?string $watermark_image;

    public string $watermark_position;

    public int $watermark_opacity;

    public int $watermark_width;

    public int $watermark_margin;

    // الميزات
    public bool $comments_enabled;

    public bool $maintenance_mode;

    // الموقع الجغرافي
    public ?string $latitude;

    public ?string $longitude;

    // ─── Tab 2: خادم البريد ─────────────────────────────────────────
    public string $mail_mailer;

    public string $mail_host;

    public int $mail_port;

    public string $mail_encryption;

    public string $mail_from_name;

    public string $mail_from_email;

    public string $mail_username;

    public string $mail_password;

    // ─── Tab 3: روابط التواصل ───────────────────────────────────────
    public string $social_facebook;

    public string $social_facebook_page_id;

    public string $social_twitter_x;

    public string $social_instagram;

    public string $social_linkedin;

    public string $social_youtube;

    public string $social_tiktok;

    public string $social_whatsapp;

    public string $social_whatsapp_channel;

    // ─── Tab 4: التتبع والتحليلات ───────────────────────────────────
    public string $analytics_google_meta_tag;

    public string $analytics_google_analytics;

    public string $analytics_facebook_pixel;

    public string $analytics_facebook_page_id;

    public string $analytics_tiktok_pixel;

    public string $analytics_instagram_pixel;

    public string $analytics_other_meta;

    // ─── Tab 5: أتمتة النسخ الاحتياطي ──────────────────────────────
    public bool $backup_auto_daily;

    public bool $backup_auto_weekly;

    public int $backup_retention_days;

    public bool $backup_cleanup_old;

    public static function group(): string
    {
        return 'general';
    }

    public static function encrypted(): array
    {
        return ['mail_password'];
    }
}
