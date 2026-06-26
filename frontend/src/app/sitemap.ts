import type { MetadataRoute } from 'next';

import { env } from '@/lib/env';

// Extensible sitemap. Static routes today; content sitemaps (Articles / News / Videos / Categories /
// Tags / Authors) plug in here later — likely proxied from the backend's content sitemaps for
// 100k+ scale — WITHOUT restructuring this entry point.
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const base = env.siteUrl;
  const staticRoutes: { path: string; priority: number; changeFrequency: MetadataRoute.Sitemap[number]['changeFrequency'] }[] = [
    { path: '/', priority: 1, changeFrequency: 'hourly' },
  ];

  return staticRoutes.map((r) => ({
    url: `${base}${r.path}`,
    lastModified: new Date(),
    changeFrequency: r.changeFrequency,
    priority: r.priority,
  }));
}
