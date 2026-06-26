import 'server-only';
import type { Metadata } from 'next';

import { env } from './env';
import { getSiteSettings, type SiteSettings } from './site-settings';

// Per-page SEO inputs. Pages pass their own title/description/path; everything else (brand, defaults,
// icons, verification) comes from Site Settings. NO hardcoded SEO content.
export interface PageSeo {
  title?: string;
  description?: string;
  path?: string;
  image?: string;
  type?: 'website' | 'article';
  keywords?: string[];
}

function toKeywords(k: SiteSettings['keywords']): string[] | undefined {
  if (!k) return undefined;
  const arr = Array.isArray(k) ? k : k.split(',');
  const out = arr.map((s) => s.trim()).filter(Boolean);
  return out.length ? out : undefined;
}

function otherVerification(s: SiteSettings | null): Record<string, string> | undefined {
  const o: Record<string, string> = {};
  if (s?.verification?.bing) o['msvalidate.01'] = s.verification.bing;
  if (s?.verification?.facebook_domain) o['facebook-domain-verification'] = s.verification.facebook_domain;
  if (s?.verification?.other) {
    for (const [k, v] of Object.entries(s.verification.other)) if (v) o[k] = v;
  }
  return Object.keys(o).length ? o : undefined;
}

// Builds Next Metadata entirely from Site Settings (+ optional per-page overrides). Used by the root
// layout and every future page/route.
export async function buildMetadata(page: PageSeo = {}): Promise<Metadata> {
  const s = await getSiteSettings();
  const siteName = s?.site_name?.trim() || '';
  const resolvedTitle = page.title ?? s?.meta_title ?? (siteName || undefined);
  const description = page.description ?? s?.meta_description ?? (s?.description?.trim() || undefined);
  const keywords = page.keywords ?? toKeywords(s?.keywords);
  const ogImage = page.image ?? s?.og_image ?? s?.logo_light ?? undefined;
  const favicon = s?.favicon ?? undefined;
  const apple = s?.apple_touch_icon ?? s?.favicon ?? undefined;
  const path = page.path ?? '/';

  return {
    metadataBase: new URL(env.siteUrl),
    title: page.title
      ? page.title
      : { default: siteName || 'AlphaCMS', template: siteName ? `%s — ${siteName}` : '%s' },
    description,
    keywords,
    applicationName: siteName || undefined,
    alternates: { canonical: path },
    icons: {
      icon: favicon ? [{ url: favicon }] : undefined,
      shortcut: favicon ?? undefined,
      apple: apple ? [{ url: apple }] : undefined,
    },
    manifest: '/manifest.webmanifest',
    openGraph: {
      type: page.type ?? 'website',
      siteName: siteName || undefined,
      title: resolvedTitle,
      description,
      url: path,
      images: ogImage ? [{ url: ogImage }] : undefined,
      locale: 'ar_AR',
    },
    twitter: {
      card: 'summary_large_image',
      title: resolvedTitle,
      description,
      images: ogImage ? [ogImage] : undefined,
    },
    verification: {
      google: s?.verification?.google ?? undefined,
      other: otherVerification(s),
    },
    // Environment awareness: only production is indexable.
    robots: env.isProd ? { index: true, follow: true } : { index: false, follow: false },
  };
}
