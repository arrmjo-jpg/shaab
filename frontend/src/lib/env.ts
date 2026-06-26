// Infrastructure env (config, not content). Server-side only.
export const env = {
  /** Kept backend API base. */
  apiBaseUrl: (process.env.API_BASE_URL ?? '').replace(/\/$/, ''),
  /** Public site origin — canonical / OG / sitemap / robots base. */
  siteUrl: (process.env.SITE_URL ?? 'http://localhost:3000').replace(/\/$/, ''),
  /** OpenWeather API key — server-only (weather widget + /weather page). Free key works on data/2.5/weather; One Call 3.0 needs a paid subscription. */
  openWeatherKey: process.env.OPENWEATHER_API_KEY ?? '',
  isProd: process.env.NODE_ENV === 'production',
  /** ترويسة نداءات الخادم-لخادم إلى الباك إند — توكن داخليّ يتجاوز حارس throttle:public.read (SSR ليس عميلاً
   *  عامّاً). تُمرَّر في `headers` لقراءات lib الخادميّة. غير مُعرَّف ⇒ undefined (بلا ترويسة، سلوك سابق). */
  internalHeaders: (process.env.INTERNAL_API_TOKEN
    ? { 'X-Internal-Token': process.env.INTERNAL_API_TOKEN }
    : undefined) as Record<string, string> | undefined,
};
