<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Tab 1: معلومات الموقع
        $this->migrator->add('general.site_name', 'AlphaCMS');
        $this->migrator->add('general.site_email', '');
        $this->migrator->add('general.site_url', '');
        $this->migrator->add('general.timezone', 'Asia/Amman');
        $this->migrator->add('general.site_phone', '');
        $this->migrator->add('general.copyright_text', '');
        $this->migrator->add('general.footer_extra_text', '');
        $this->migrator->add('general.cookie_policy_text', '');

        $this->migrator->add('general.logo_light', null);
        $this->migrator->add('general.logo_dark', null);
        $this->migrator->add('general.favicon', null);

        $this->migrator->add('general.watermark_enabled', false);
        $this->migrator->add('general.watermark_image', null);
        $this->migrator->add('general.watermark_position', 'bottom-left');
        $this->migrator->add('general.watermark_opacity', 80);
        $this->migrator->add('general.watermark_width', 100);
        $this->migrator->add('general.watermark_margin', 20);

        $this->migrator->add('general.comments_enabled', true);
        $this->migrator->add('general.maintenance_mode', false);

        $this->migrator->add('general.latitude', null);
        $this->migrator->add('general.longitude', null);

        // Tab 2: خادم البريد
        $this->migrator->add('general.mail_mailer', 'smtp');
        $this->migrator->add('general.mail_host', '');
        $this->migrator->add('general.mail_port', 587);
        $this->migrator->add('general.mail_encryption', 'tls');
        $this->migrator->add('general.mail_from_name', '');
        $this->migrator->add('general.mail_from_email', '');
        $this->migrator->add('general.mail_username', '');
        $this->migrator->addEncrypted('general.mail_password', '');

        // Tab 3: روابط التواصل
        $this->migrator->add('general.social_facebook', '');
        $this->migrator->add('general.social_facebook_page_id', '');
        $this->migrator->add('general.social_twitter_x', '');
        $this->migrator->add('general.social_instagram', '');
        $this->migrator->add('general.social_linkedin', '');
        $this->migrator->add('general.social_youtube', '');
        $this->migrator->add('general.social_tiktok', '');
        $this->migrator->add('general.social_whatsapp', '');
        $this->migrator->add('general.social_whatsapp_channel', '');

        // Tab 4: التتبع والتحليلات
        $this->migrator->add('general.analytics_google_meta_tag', '');
        $this->migrator->add('general.analytics_google_analytics', '');
        $this->migrator->add('general.analytics_facebook_pixel', '');
        $this->migrator->add('general.analytics_facebook_page_id', '');
        $this->migrator->add('general.analytics_tiktok_pixel', '');
        $this->migrator->add('general.analytics_instagram_pixel', '');
        $this->migrator->add('general.analytics_other_meta', '');

        // Tab 5: أتمتة النسخ الاحتياطي
        $this->migrator->add('general.backup_auto_daily', false);
        $this->migrator->add('general.backup_auto_weekly', false);
        $this->migrator->add('general.backup_retention_days', 7);
        $this->migrator->add('general.backup_cleanup_old', true);
    }
};
