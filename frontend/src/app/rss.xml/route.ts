import { env } from '@/lib/env';
import { getSiteSettings } from '@/lib/site-settings';

export const revalidate = 300;

function esc(v: string): string {
  return v.replace(/[<>&'"]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', "'": '&apos;', '"': '&quot;' })[c] as string);
}

// RSS infrastructure — channel metadata from Site Settings. Item generation plugs in when content
// (articles/news) lands, without restructuring this endpoint.
export async function GET(): Promise<Response> {
  const s = await getSiteSettings();
  const site = env.siteUrl;
  const title = s?.site_name?.trim() || 'AlphaCMS';
  const description = s?.description?.trim() || title;

  const items = ''; // no content yet — ready for <item>…</item> entries

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>${esc(title)}</title>
    <link>${site}</link>
    <description>${esc(description)}</description>
    <language>ar</language>
    <atom:link href="${site}/rss.xml" rel="self" type="application/rss+xml"/>
${items}  </channel>
</rss>`;

  return new Response(xml, {
    headers: {
      'Content-Type': 'application/rss+xml; charset=utf-8',
      'Cache-Control': 's-maxage=300, stale-while-revalidate=600',
    },
  });
}
