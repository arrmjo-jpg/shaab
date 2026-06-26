export interface SocialLoginData {
  google_enabled: boolean;
  google_client_id: string;
  google_client_secret: string | null;
  google_client_secret_configured: boolean;
  google_redirect_url: string;
  facebook_enabled: boolean;
  facebook_client_id: string;
  facebook_client_secret: string | null;
  facebook_client_secret_configured: boolean;
  facebook_redirect_url: string;
}

export interface RecaptchaData {
  enabled: boolean;
  version: string;
  site_key: string;
  secret_key: string | null;
  secret_key_configured: boolean;
  score: number;
}

export interface FirebaseData {
  enabled: boolean;
  project_id: string;
  api_key: string | null;
  api_key_configured: boolean;
  auth_domain: string;
  database_url: string;
  storage_bucket: string;
  messaging_sender_id: string;
  app_id: string;
  measurement_id: string;
  token_uri: string;
  service_account_configured: boolean;
  credentials_path: string;
}

export interface GoogleMapsData {
  enabled: boolean;
  frontend_key: string | null;
  frontend_key_configured: boolean;
  server_key: string | null;
  server_key_configured: boolean;
}

export interface AiProviderOpenAI {
  api_key: string | null;
  api_key_configured: boolean;
  base_url: string;
  model: string;
  temperature: number;
  max_tokens: number;
  timeout: number;
  writing_style: string;
}

export interface AiProviderGemini {
  api_key: string | null;
  api_key_configured: boolean;
  model: string;
  temperature: number;
  max_tokens: number;
  timeout: number;
}

export interface AiPrompts {
  news_prompt: string;
  article_prompt: string;
  default_prompt: string;
  rewrite_prompt: string;
  seo_prompt: string;
  tags_prompt: string;
}

export interface AiData {
  ai_enabled: boolean;
  provider: string;
  openai: AiProviderOpenAI;
  gemini: AiProviderGemini;
  prompts: AiPrompts;
}

export interface WhatsappData {
  enabled: boolean;
  instance_id: string;
  token: string | null;
  token_configured: boolean;
  base_url: string;
  batch_size: number;
  delay_seconds: number;
}

export interface AppLinksData {
  google_play_url: string;
  apple_store_url: string;
  tv_url: string;
}

export interface SportmonksData {
  enabled: boolean;
  base_url: string;
  api_key: string | null;
  api_key_configured: boolean;
}

export interface OpenweatherData {
  enabled: boolean;
  base_url: string;
  units: string;
  default_language: string;
  api_key: string | null;
  api_key_configured: boolean;
}

export interface GeminiTtsData {
  enabled: boolean;
  api_key: string | null;
  api_key_configured: boolean;
}

export interface IntegrationsData {
  sportmonks: SportmonksData;
  openweather: OpenweatherData;
  gemini_tts: GeminiTtsData;
}

export interface ThirdPartyData {
  social_login: SocialLoginData;
  recaptcha: RecaptchaData;
  firebase: FirebaseData;
  google_maps: GoogleMapsData;
  ai: AiData;
  whatsapp: WhatsappData;
  app_links: AppLinksData;
  integrations: IntegrationsData;
}

export type ThirdPartyUpdatePayload = Record<string, string | number | boolean | null>;
