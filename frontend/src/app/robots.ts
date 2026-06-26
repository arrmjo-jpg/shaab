import type { MetadataRoute } from 'next';

import { env } from '@/lib/env';

// Dynamic, environment-aware robots. Non-production is fully disallowed; production allows crawl
// (minus private areas) and advertises the sitemap.
export default function robots(): MetadataRoute.Robots {
  if (!env.isProd) {
    return { rules: [{ userAgent: '*', disallow: '/' }] };
  }
  return {
    rules: [{ userAgent: '*', allow: '/', disallow: ['/api/', '/account/'] }],
    sitemap: `${env.siteUrl}/sitemap.xml`,
    host: env.siteUrl,
  };
}
