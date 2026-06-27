// Infrastructure env (config, not content). Server-side only.
export const env = {
  /** Kept backend API base. */
  apiBaseUrl: (process.env.API_BASE_URL ?? '').replace(/\/$/, ''),

  /** Public site origin — canonical / OG / sitemap / robots base. */
  siteUrl: (process.env.SITE_URL || 'http://localhost:3000').replace(/\/$/, ''),

  /** OpenWeather API key — server-only (weather widget + /weather page). */
  openWeatherKey: process.env.OPENWEATHER_API_KEY ?? '',

  isProd: process.env.NODE_ENV === 'production',

  /**
   * Headers for server-to-server requests.
   */
  internalHeaders: {
    ...(process.env.INTERNAL_API_TOKEN
      ? { 'X-Internal-Token': process.env.INTERNAL_API_TOKEN }
      : {}),

    ...(process.env.INTERNAL_API_HOST
      ? { Host: process.env.INTERNAL_API_HOST }
      : {}),
  } as Record<string, string>,
};