<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد إعدادات الطرف الثالث. كل الأسرار مُقنَّعة مع *_configured.
 */
class ThirdPartySettingsResource extends JsonResource
{
    private function masked(?string $value): ?string
    {
        return ($value === null || $value === '') ? null : '********';
    }

    private function isSet(?string $value): bool
    {
        return $value !== null && $value !== '';
    }

    public function toArray(Request $request): array
    {
        $s = $this->resource;

        return [
            'social_login' => [
                'google_enabled' => $s->google_enabled,
                'google_client_id' => $s->google_client_id,
                'google_client_secret' => $this->masked($s->google_client_secret),
                'google_client_secret_configured' => $this->isSet($s->google_client_secret),
                'google_redirect_url' => $s->google_redirect_url,
                'facebook_enabled' => $s->facebook_enabled,
                'facebook_client_id' => $s->facebook_client_id,
                'facebook_client_secret' => $this->masked($s->facebook_client_secret),
                'facebook_client_secret_configured' => $this->isSet($s->facebook_client_secret),
                'facebook_redirect_url' => $s->facebook_redirect_url,
            ],
            'recaptcha' => [
                'enabled' => $s->recaptcha_enabled,
                'version' => $s->recaptcha_version,
                'site_key' => $s->recaptcha_site_key,
                'secret_key' => $this->masked($s->recaptcha_secret_key),
                'secret_key_configured' => $this->isSet($s->recaptcha_secret_key),
                'score' => $s->recaptcha_score,
            ],
            'firebase' => [
                'enabled' => $s->firebase_enabled,
                'project_id' => $s->firebase_project_id,
                'api_key' => $this->masked($s->firebase_api_key),
                'api_key_configured' => $this->isSet($s->firebase_api_key),
                'auth_domain' => $s->firebase_auth_domain,
                'database_url' => $s->firebase_database_url,
                'storage_bucket' => $s->firebase_storage_bucket,
                'messaging_sender_id' => $s->firebase_messaging_sender_id,
                'app_id' => $s->firebase_app_id,
                'measurement_id' => $s->firebase_measurement_id,
                'token_uri' => $s->firebase_token_uri,
                'service_account_configured' => $this->isSet($s->firebase_service_account_json),
                'credentials_path' => $s->firebase_credentials_path,
            ],
            'google_maps' => [
                'enabled' => $s->maps_enabled,
                'frontend_key' => $this->masked($s->maps_frontend_key),
                'frontend_key_configured' => $this->isSet($s->maps_frontend_key),
                'server_key' => $this->masked($s->maps_server_key),
                'server_key_configured' => $this->isSet($s->maps_server_key),
            ],
            'ai' => [
                'ai_enabled' => $s->ai_enabled,
                'provider' => $s->ai_provider,
                'openai' => [
                    'api_key' => $this->masked($s->openai_api_key),
                    'api_key_configured' => $this->isSet($s->openai_api_key),
                    'base_url' => $s->openai_base_url,
                    'model' => $s->openai_model,
                    'temperature' => $s->openai_temperature,
                    'max_tokens' => $s->openai_max_tokens,
                    'timeout' => $s->openai_timeout,
                    'writing_style' => $s->openai_writing_style,
                ],
                'gemini' => [
                    'api_key' => $this->masked($s->gemini_api_key),
                    'api_key_configured' => $this->isSet($s->gemini_api_key),
                    'model' => $s->gemini_model,
                    'temperature' => $s->gemini_temperature,
                    'max_tokens' => $s->gemini_max_tokens,
                    'timeout' => $s->gemini_timeout,
                ],
                'prompts' => [
                    'news_prompt' => $s->ai_news_prompt,
                    'article_prompt' => $s->ai_article_prompt,
                    'default_prompt' => $s->ai_default_prompt,
                    'rewrite_prompt' => $s->ai_rewrite_prompt,
                    'seo_prompt' => $s->ai_seo_prompt,
                    'tags_prompt' => $s->ai_tags_prompt,
                ],
            ],
            'whatsapp' => [
                'enabled' => $s->whatsapp_enabled,
                'instance_id' => $s->whatsapp_instance_id,
                'token' => $this->masked($s->whatsapp_token),
                'token_configured' => $this->isSet($s->whatsapp_token),
                'base_url' => $s->whatsapp_base_url,
                'batch_size' => $s->whatsapp_batch_size,
                'delay_seconds' => $s->whatsapp_delay_seconds,
            ],
            'app_links' => [
                'google_play_url' => $s->app_google_play_url,
                'apple_store_url' => $s->app_apple_store_url,
                'tv_url' => $s->app_tv_url,
            ],
            'integrations' => [
                'sportmonks' => [
                    'enabled' => $s->sportmonks_enabled,
                    'base_url' => $s->sportmonks_base_url,
                    'api_key' => $this->masked($s->sportmonks_api_key),
                    'api_key_configured' => $this->isSet($s->sportmonks_api_key),
                ],
                'openweather' => [
                    'enabled' => $s->openweather_enabled,
                    'base_url' => $s->openweather_base_url,
                    'units' => $s->openweather_units,
                    'default_language' => $s->openweather_default_language,
                    'api_key' => $this->masked($s->openweather_api_key),
                    'api_key_configured' => $this->isSet($s->openweather_api_key),
                ],
                'gemini_tts' => [
                    'enabled' => $s->gemini_tts_enabled,
                    'api_key' => $this->masked($s->gemini_tts_api_key),
                    'api_key_configured' => $this->isSet($s->gemini_tts_api_key),
                ],
            ],
        ];
    }
}
