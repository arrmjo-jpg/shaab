<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * إعدادات الطرف الثالث: تسجيل الدخول الاجتماعي، reCAPTCHA،
 * Firebase، خرائط جوجل، مزوّدو الذكاء الاصطناعي، واتساب، روابط التطبيقات.
 */
class ThirdPartySettings extends Settings
{
    // ─── Tab 1: تسجيل الدخول الاجتماعي ─────────────────────────────
    public bool $google_enabled;

    public string $google_client_id;

    public string $google_client_secret;

    public string $google_redirect_url;

    public bool $facebook_enabled;

    public string $facebook_client_id;

    public string $facebook_client_secret;

    public string $facebook_redirect_url;

    // ─── Tab 2: reCAPTCHA ───────────────────────────────────────────
    public bool $recaptcha_enabled;

    public string $recaptcha_version;

    public string $recaptcha_site_key;

    public string $recaptcha_secret_key;

    public float $recaptcha_score;

    // ─── Tab 3: Firebase ────────────────────────────────────────────
    public bool $firebase_enabled;

    public string $firebase_project_id;

    public string $firebase_api_key;

    public string $firebase_auth_domain;

    public string $firebase_database_url;

    public string $firebase_storage_bucket;

    public string $firebase_messaging_sender_id;

    public string $firebase_app_id;

    public string $firebase_measurement_id;

    public string $firebase_token_uri;

    public string $firebase_service_account_json;

    public string $firebase_credentials_path;

    // ─── Tab 4: خرائط جوجل ──────────────────────────────────────────
    public bool $maps_enabled;

    public string $maps_frontend_key;

    public string $maps_server_key;

    // ─── Tab 5: مزوّدو الذكاء الاصطناعي ────────────────────────────
    public bool $ai_enabled;

    public string $ai_provider;

    public string $openai_api_key;

    public string $openai_base_url;

    public string $openai_model;

    public float $openai_temperature;

    public int $openai_max_tokens;

    public int $openai_timeout;

    public string $openai_writing_style;

    public string $gemini_api_key;

    public string $gemini_model;

    public float $gemini_temperature;

    public int $gemini_max_tokens;

    public int $gemini_timeout;

    public string $ai_news_prompt;

    public string $ai_article_prompt;

    public string $ai_default_prompt;

    public string $ai_rewrite_prompt;

    public string $ai_seo_prompt;

    public string $ai_tags_prompt;

    // ─── Tab 6: واتساب (UltraMsg) ───────────────────────────────────
    public bool $whatsapp_enabled;

    public string $whatsapp_instance_id;

    public string $whatsapp_token;

    public string $whatsapp_base_url;

    public int $whatsapp_batch_size;

    public int $whatsapp_delay_seconds;

    // ─── Tab 7: روابط التطبيقات ────────────────────────────────────
    public string $app_google_play_url;

    public string $app_apple_store_url;

    public string $app_tv_url;

    // ─── Tab 8: تكاملات API خارجية ─────────────────────────────────
    public bool $sportmonks_enabled;

    public string $sportmonks_api_key;

    public string $sportmonks_base_url;

    public bool $openweather_enabled;

    public string $openweather_api_key;

    public string $openweather_base_url;

    public string $openweather_units;

    public string $openweather_default_language;

    // ─── Google Gemini Text-to-Speech (استماع للمقال صوتيًّا) ───────
    public bool $gemini_tts_enabled;

    public string $gemini_tts_api_key;

    public static function group(): string
    {
        return 'third_party';
    }

    public static function encrypted(): array
    {
        return [
            'google_client_secret',
            'facebook_client_secret',
            'recaptcha_secret_key',
            'firebase_api_key',
            'firebase_service_account_json',
            'maps_frontend_key',
            'maps_server_key',
            'openai_api_key',
            'gemini_api_key',
            'whatsapp_token',
            'sportmonks_api_key',
            'openweather_api_key',
            'gemini_tts_api_key',
        ];
    }
}
