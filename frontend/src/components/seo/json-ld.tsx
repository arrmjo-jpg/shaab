import { env } from '@/lib/env';
import { getSiteSettings, socialUrls } from '@/lib/site-settings';

// JSON-LD layer — NewsMediaOrganization + WebSite (with SearchAction). All values from Site Settings.
// Ready to extend with NewsArticle/VideoObject on content pages later. Server Component.
export async function JsonLd() {
  const s = await getSiteSettings();
  if (!s) return null;

  const url = env.siteUrl;
  const sameAs = socialUrls(s);

  const organization = {
    '@context': 'https://schema.org',
    '@type': 'NewsMediaOrganization',
    name: s.site_name || undefined,
    url,
    ...(s.logo_light ? { logo: { '@type': 'ImageObject', url: s.logo_light } } : {}),
    ...(sameAs.length ? { sameAs } : {}),
    ...(s.email || s.phone
      ? {
          contactPoint: {
            '@type': 'ContactPoint',
            contactType: 'customer service',
            ...(s.email ? { email: s.email } : {}),
            ...(s.phone ? { telephone: s.phone } : {}),
          },
        }
      : {}),
  };

  const website = {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: s.site_name || undefined,
    url,
    potentialAction: {
      '@type': 'SearchAction',
      target: { '@type': 'EntryPoint', urlTemplate: `${url}/search?q={search_term_string}` },
      'query-input': 'required name=search_term_string',
    },
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(organization) }} />
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(website) }} />
    </>
  );
}
