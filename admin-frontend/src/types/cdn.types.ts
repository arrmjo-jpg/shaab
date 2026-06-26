export interface CdnStats {
  purge_success: number;
  purge_failed: number;
  purged_urls: number;
  last_purge_at: string | null;
  last_test_ok: boolean | null;
  last_test_at: string | null;
}

export interface CdnStatus {
  enabled: boolean;
  configured: boolean;
  plan: string;
  auto_purge: boolean;
  stats: CdnStats;
}

export interface CdnSettings {
  cdn_enabled: boolean;
  auto_purge: boolean;
  plan: string;
  zone_id: string;
  api_token: string | null;
  api_token_configured: boolean;
}

export type CdnUpdatePayload = Record<string, string | boolean>;
