<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Tab 1: تسجيل الدخول الاجتماعي
        $this->migrator->add('third_party.google_enabled', false);
        $this->migrator->add('third_party.google_client_id', '');
        $this->migrator->addEncrypted('third_party.google_client_secret', '');
        $this->migrator->add('third_party.google_redirect_url', '');

        $this->migrator->add('third_party.facebook_enabled', false);
        $this->migrator->add('third_party.facebook_client_id', '');
        $this->migrator->addEncrypted('third_party.facebook_client_secret', '');
        $this->migrator->add('third_party.facebook_redirect_url', '');

        // Tab 2: reCAPTCHA
        $this->migrator->add('third_party.recaptcha_enabled', false);
        $this->migrator->add('third_party.recaptcha_version', 'v3');
        $this->migrator->add('third_party.recaptcha_site_key', '');
        $this->migrator->addEncrypted('third_party.recaptcha_secret_key', '');
        $this->migrator->add('third_party.recaptcha_score', 0.5);

        // Tab 3: Firebase
        $this->migrator->add('third_party.firebase_enabled', false);
        $this->migrator->add('third_party.firebase_project_id', '');
        $this->migrator->addEncrypted('third_party.firebase_api_key', '');
        $this->migrator->add('third_party.firebase_auth_domain', '');
        $this->migrator->add('third_party.firebase_database_url', '');
        $this->migrator->add('third_party.firebase_storage_bucket', '');
        $this->migrator->add('third_party.firebase_messaging_sender_id', '');
        $this->migrator->add('third_party.firebase_app_id', '');
        $this->migrator->add('third_party.firebase_measurement_id', '');
        $this->migrator->add('third_party.firebase_token_uri', 'https://oauth2.googleapis.com/token');
        $this->migrator->addEncrypted('third_party.firebase_service_account_json', '');
        $this->migrator->add('third_party.firebase_credentials_path', '');

        // Tab 4: خرائط جوجل
        $this->migrator->add('third_party.maps_enabled', false);
        $this->migrator->addEncrypted('third_party.maps_frontend_key', '');
        $this->migrator->addEncrypted('third_party.maps_server_key', '');

        // Tab 5: مزوّدو الذكاء الاصطناعي
        $this->migrator->add('third_party.ai_enabled', false);
        $this->migrator->add('third_party.ai_provider', 'openai');

        $this->migrator->addEncrypted('third_party.openai_api_key', '');
        $this->migrator->add('third_party.openai_base_url', 'https://api.openai.com/v1');
        $this->migrator->add('third_party.openai_model', 'gpt-4o-mini');
        $this->migrator->add('third_party.openai_temperature', 0.7);
        $this->migrator->add('third_party.openai_max_tokens', 1000);
        $this->migrator->add('third_party.openai_timeout', 30);
        $this->migrator->add('third_party.openai_writing_style', '');

        $this->migrator->addEncrypted('third_party.gemini_api_key', '');
        $this->migrator->add('third_party.gemini_model', 'gemini-2.0-flash');
        $this->migrator->add('third_party.gemini_temperature', 0.7);
        $this->migrator->add('third_party.gemini_max_tokens', 1000);
        $this->migrator->add('third_party.gemini_timeout', 30);

        $this->migrator->add('third_party.ai_news_prompt', '');
        $this->migrator->add('third_party.ai_article_prompt', '');
        $this->migrator->add('third_party.ai_default_prompt', '');
        $this->migrator->add('third_party.ai_rewrite_prompt', '');
        $this->migrator->add('third_party.ai_seo_prompt', '');
        $this->migrator->add('third_party.ai_tags_prompt', '');

        // Tab 6: واتساب (UltraMsg)
        $this->migrator->add('third_party.whatsapp_enabled', false);
        $this->migrator->add('third_party.whatsapp_instance_id', '');
        $this->migrator->addEncrypted('third_party.whatsapp_token', '');
        $this->migrator->add('third_party.whatsapp_base_url', 'https://api.ultramsg.com');
        $this->migrator->add('third_party.whatsapp_batch_size', 10);
        $this->migrator->add('third_party.whatsapp_delay_seconds', 5);

        // Tab 7: روابط التطبيقات
        $this->migrator->add('third_party.app_google_play_url', '');
        $this->migrator->add('third_party.app_apple_store_url', '');
        $this->migrator->add('third_party.app_tv_url', '');
    }
};
