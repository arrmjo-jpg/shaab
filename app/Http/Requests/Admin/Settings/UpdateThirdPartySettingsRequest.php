<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use App\Enums\AiProvider;
use App\Enums\RecaptchaVersion;
use App\Enums\WeatherUnits;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateThirdPartySettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Social login
            'google_enabled' => ['sometimes', 'boolean'],
            'google_client_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'google_client_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'google_redirect_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'facebook_enabled' => ['sometimes', 'boolean'],
            'facebook_client_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook_client_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook_redirect_url' => ['sometimes', 'nullable', 'url', 'max:255'],

            // reCAPTCHA — v3 يتطلب نطاق score
            'recaptcha_enabled' => ['sometimes', 'boolean'],
            'recaptcha_version' => ['sometimes', Rule::enum(RecaptchaVersion::class)],
            'recaptcha_site_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recaptcha_secret_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recaptcha_score' => [
                'nullable', 'numeric', 'between:0,1',
                Rule::requiredIf(fn (): bool => $this->input('recaptcha_version') === RecaptchaVersion::V3->value),
            ],

            // Firebase (الرفع عبر endpoint مستقل — هنا الحقول النصية فقط)
            'firebase_enabled' => ['sometimes', 'boolean'],
            'firebase_project_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'firebase_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'firebase_auth_domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'firebase_database_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'firebase_storage_bucket' => ['sometimes', 'nullable', 'string', 'max:255'],
            'firebase_messaging_sender_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'firebase_app_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'firebase_measurement_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'firebase_token_uri' => ['sometimes', 'nullable', 'url', 'max:255'],

            // Google Maps
            'maps_enabled' => ['sometimes', 'boolean'],
            'maps_frontend_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'maps_server_key' => ['sometimes', 'nullable', 'string', 'max:255'],

            // AI — مزوّد واحد فعّال
            'ai_enabled' => ['sometimes', 'boolean'],
            'ai_provider' => ['sometimes', Rule::enum(AiProvider::class)],
            'openai_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'openai_base_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'openai_model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'openai_temperature' => ['sometimes', 'numeric', 'between:0,2'],
            'openai_max_tokens' => ['sometimes', 'integer', 'min:1', 'max:128000'],
            'openai_timeout' => ['sometimes', 'integer', 'min:1', 'max:300'],
            'openai_writing_style' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'gemini_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gemini_model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gemini_temperature' => ['sometimes', 'numeric', 'between:0,2'],
            'gemini_max_tokens' => ['sometimes', 'integer', 'min:1', 'max:128000'],
            'gemini_timeout' => ['sometimes', 'integer', 'min:1', 'max:300'],
            'ai_news_prompt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'ai_article_prompt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'ai_default_prompt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'ai_rewrite_prompt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'ai_seo_prompt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'ai_tags_prompt' => ['sometimes', 'nullable', 'string', 'max:5000'],

            // WhatsApp (UltraMsg)
            'whatsapp_enabled' => ['sometimes', 'boolean'],
            'whatsapp_instance_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'whatsapp_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_base_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'whatsapp_batch_size' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'whatsapp_delay_seconds' => ['sometimes', 'integer', 'min:0', 'max:3600'],

            // App links
            'app_google_play_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'app_apple_store_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'app_tv_url' => ['sometimes', 'nullable', 'url', 'max:255'],

            // External API integrations
            'sportmonks_enabled' => ['sometimes', 'boolean'],
            'sportmonks_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sportmonks_base_url' => ['sometimes', 'required', 'url', 'max:255'],

            'openweather_enabled' => ['sometimes', 'boolean'],
            'openweather_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'openweather_base_url' => ['sometimes', 'required', 'url', 'max:255'],
            'openweather_units' => ['sometimes', Rule::enum(WeatherUnits::class)],
            'openweather_default_language' => ['sometimes', 'nullable', 'string', 'max:10'],

            // Google Gemini Text-to-Speech
            'gemini_tts_enabled' => ['sometimes', 'boolean'],
            'gemini_tts_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
