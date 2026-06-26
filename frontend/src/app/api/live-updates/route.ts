import { type NextRequest, NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF استطلاع التغطية الحيّة — وكيل لنقطة GET /{locale}/articles/{slug}/live-updates (مُصمَّمة للـpolling بـETag).
// يمرّر If-None-Match فيردّ 304 دون جسم عند عدم التغيّر (توفير نطاق كما صُمّم الباك إند). لا منطق جديد — تمرير فقط.
export async function GET(request: NextRequest): Promise<NextResponse> {
  if (!env.apiBaseUrl) return NextResponse.json({ error: 'no_api' }, { status: 502 });

  const slug = request.nextUrl.searchParams.get('slug') ?? '';
  if (!slug) return NextResponse.json({ error: 'bad_slug' }, { status: 400 });

  const headers: Record<string, string> = { Accept: 'application/json' };
  const inm = request.headers.get('if-none-match');
  if (inm) headers['If-None-Match'] = inm;

  try {
    const res = await fetch(
      `${env.apiBaseUrl}/api/v1/ar/articles/${encodeURIComponent(slug)}/live-updates?per_page=40`,
      { headers, cache: 'no-store' },
    );
    const etag = res.headers.get('etag') ?? '';
    if (res.status === 304) {
      return new NextResponse(null, { status: 304, headers: etag ? { ETag: etag } : undefined });
    }
    const json: unknown = await res.json().catch(() => null);
    const out = NextResponse.json(json, { status: res.status });
    if (etag) out.headers.set('ETag', etag);
    return out;
  } catch {
    return NextResponse.json({ error: 'upstream' }, { status: 502 });
  }
}
