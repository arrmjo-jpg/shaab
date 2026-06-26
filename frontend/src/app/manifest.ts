import type { MetadataRoute } from 'next';

import { getSiteSettings } from '@/lib/site-settings';

// Web App Manifest — name/description/icons from Site Settings (not static files). RTL/Arabic.
export default async function manifest(): Promise<MetadataRoute.Manifest> {
  const s = await getSiteSettings();
  const name = s?.site_name?.trim() || 'AlphaCMS';

  return {
    name,
    short_name: name,
    description: s?.description?.trim() || undefined,
    start_url: '/',
    display: 'standalone',
    dir: 'rtl',
    lang: 'ar',
    background_color: '#ffffff',
    theme_color: '#c8102e',
    icons: s?.favicon ? [{ src: s.favicon, sizes: 'any', type: 'image/png' }] : [],
  };
}
