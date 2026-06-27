// Infrastructure env (config, not content). Server-side only.
export const env = {
  apiBaseUrl: (process.env.API_BASE_URL ?? '').replace(/\/$/, ''),
  siteUrl: (process.env.SITE_URL || 'http://localhost:3000').replace(/\/$/, ''),
  openWeatherKey: process.env.OPENWEATHER_API_KEY ?? '',
  isProd: process.env.NODE_ENV === 'production',
  internalHeaders: (process.env.INTERNAL_API_TOKEN
    ? { 'X-Internal-Token': process.env.INTERNAL_API_TOKEN }
    : undefined) as Record<string, string> | undefined,
};