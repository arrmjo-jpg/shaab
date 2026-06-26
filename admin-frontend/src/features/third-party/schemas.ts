import { z } from 'zod';

const optUrl = z.string().url('thirdParty:validation.url').or(z.literal(''));

export const socialLoginSchema = z.object({
  google_enabled: z.boolean(),
  google_client_id: z.string(),
  google_client_secret: z.string(),
  google_redirect_url: optUrl,
  facebook_enabled: z.boolean(),
  facebook_client_id: z.string(),
  facebook_client_secret: z.string(),
  facebook_redirect_url: optUrl,
});
export type SocialLoginValues = z.infer<typeof socialLoginSchema>;

export const recaptchaSchema = z
  .object({
    recaptcha_enabled: z.boolean(),
    recaptcha_version: z.string(),
    recaptcha_site_key: z.string(),
    recaptcha_secret_key: z.string(),
    recaptcha_score: z.number().min(0, 'thirdParty:validation.scoreRange').max(1, 'thirdParty:validation.scoreRange'),
  });
export type RecaptchaValues = z.infer<typeof recaptchaSchema>;

export const firebaseSchema = z.object({
  firebase_enabled: z.boolean(),
  firebase_project_id: z.string(),
  firebase_api_key: z.string(),
  firebase_auth_domain: z.string(),
  firebase_database_url: optUrl,
  firebase_storage_bucket: z.string(),
  firebase_messaging_sender_id: z.string(),
  firebase_app_id: z.string(),
  firebase_measurement_id: z.string(),
  firebase_token_uri: optUrl,
});
export type FirebaseValues = z.infer<typeof firebaseSchema>;

export const googleMapsSchema = z.object({
  maps_enabled: z.boolean(),
  maps_frontend_key: z.string(),
  maps_server_key: z.string(),
});
export type GoogleMapsValues = z.infer<typeof googleMapsSchema>;

export const aiSchema = z.object({
  ai_enabled: z.boolean(),
  ai_provider: z.string(),
  openai_api_key: z.string(),
  openai_base_url: optUrl,
  openai_model: z.string(),
  openai_temperature: z.number().min(0).max(2),
  openai_max_tokens: z.number().min(1).max(128000),
  openai_timeout: z.number().min(1).max(300),
  openai_writing_style: z.string(),
  gemini_api_key: z.string(),
  gemini_model: z.string(),
  gemini_temperature: z.number().min(0).max(2),
  gemini_max_tokens: z.number().min(1).max(128000),
  gemini_timeout: z.number().min(1).max(300),
  ai_news_prompt: z.string(),
  ai_article_prompt: z.string(),
  ai_default_prompt: z.string(),
  ai_rewrite_prompt: z.string(),
  ai_seo_prompt: z.string(),
  ai_tags_prompt: z.string(),
});
export type AiValues = z.infer<typeof aiSchema>;

export const whatsappSchema = z.object({
  whatsapp_enabled: z.boolean(),
  whatsapp_instance_id: z.string(),
  whatsapp_token: z.string(),
  whatsapp_base_url: optUrl,
  whatsapp_batch_size: z.number().min(1).max(1000),
  whatsapp_delay_seconds: z.number().min(0).max(3600),
});
export type WhatsappValues = z.infer<typeof whatsappSchema>;

export const appLinksSchema = z.object({
  app_google_play_url: optUrl,
  app_apple_store_url: optUrl,
  app_tv_url: optUrl,
});
export type AppLinksValues = z.infer<typeof appLinksSchema>;

export const integrationsSchema = z.object({
  sportmonks_enabled: z.boolean(),
  sportmonks_api_key: z.string(),
  sportmonks_base_url: z.string().url('thirdParty:validation.url'),
  openweather_enabled: z.boolean(),
  openweather_api_key: z.string(),
  openweather_base_url: z.string().url('thirdParty:validation.url'),
  openweather_units: z.string(),
  openweather_default_language: z.string().max(10),
  gemini_tts_enabled: z.boolean(),
  gemini_tts_api_key: z.string(),
});
export type IntegrationsValues = z.infer<typeof integrationsSchema>;
