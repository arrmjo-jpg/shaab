import { getSiteSettings } from '@/lib/site-settings';

// ── Unified branding · the ONLY place a logo is rendered in the app ─────────────────────────────
// `variant` selects the Site-Settings field: light → logo_light (for light surfaces, e.g. header),
// dark → logo_dark (for dark surfaces, e.g. footer). Data comes from Site Settings only.
// Missing/empty logo → site_name text fallback. NO filter/invert/brightness (no color manipulation).
// The public API (variant / className / priority) is STABLE across the future <img> → next/image
// migration — the swap happens inside this file only, consumers never change.
type LogoVariant = 'light' | 'dark';

export interface SiteLogoProps {
  variant: LogoVariant;
  className?: string;
  /** Above-the-fold logos (e.g. header) pass `priority` to load eagerly. */
  priority?: boolean;
}

export async function SiteLogo({ variant, className, priority = false }: SiteLogoProps) {
  const s = await getSiteSettings();
  const siteName = s?.site_name?.trim() || 'الشعب';
  const src = (variant === 'light' ? s?.logo_light : s?.logo_dark)?.trim() || null;

  if (!src) {
    return <span className={className}>{siteName}</span>;
  }

  return (
    // eslint-disable-next-line @next/next/no-img-element -- single swap point to next/image (Image-Platform slice); public API unchanged
    <img src={src} alt={siteName} className={className} loading={priority ? 'eager' : 'lazy'} decoding="async" />
  );
}
