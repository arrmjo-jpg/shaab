import { env } from '@/lib/env';
import { getSiteSettings } from '@/lib/site-settings';

// Performance resource hints — preconnect + dns-prefetch to the media/API origins (derived from
// Site Settings URLs + env, not hardcoded). React 19 hoists these <link>s into <head>.
// (next/font already preconnects the font origin.)
export async function ResourceHints() {
  const s = await getSiteSettings();
  const origins = new Set<string>();
  const add = (u?: string | null) => {
    if (!u) return;
    try {
      origins.add(new URL(u).origin);
    } catch {
      /* ignore non-absolute */
    }
  };
  add(env.apiBaseUrl);
  add(s?.logo_light);
  add(s?.logo_dark);
  add(s?.favicon);
  add(s?.og_image);

  const list = [...origins];
  return (
    <>
      {list.map((o) => (
        <link key={`pc-${o}`} rel="preconnect" href={o} />
      ))}
      {list.map((o) => (
        <link key={`dns-${o}`} rel="dns-prefetch" href={o} />
      ))}
    </>
  );
}
