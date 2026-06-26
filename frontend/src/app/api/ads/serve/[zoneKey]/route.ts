import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF: GET /api/ads/serve/{zoneKey} → GET /api/v1/ads/serve/{zoneKey}?locale=&device=
// عقد Cache/CDN إلزاميّ: إعلان حيّ فقط — cache:'no-store' على الجلب وعلى الاستجابة (ممنوع
// SSR/ISR/CDN/تخزين؛ رمز الانطباع صالح ضمن نافذة الدلو الخادميّة فقط). يمرّر X-Client-Id
// (فاعل ثابت/throttle). يرجع { ad } فقط. لا نظام جديد: نمط BFF القائم + env القائم.
export const dynamic = 'force-dynamic';

const NO_STORE = { 'Cache-Control': 'no-store, no-cache, must-revalidate' } as const;
const ZONE_RE = /^[a-z0-9_]+$/;

export async function GET(
  request: Request,
  { params }: { params: Promise<{ zoneKey: string }> },
): Promise<Response> {
  const { zoneKey } = await params;

  if (!env.apiBaseUrl || !ZONE_RE.test(zoneKey)) {
    return NextResponse.json({ ad: null }, { headers: NO_STORE });
  }

  const { searchParams } = new URL(request.url);
  const query = new URLSearchParams({
    locale: searchParams.get('locale') ?? 'ar',
    device: searchParams.get('device') ?? 'desktop',
  }).toString();

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/ads/serve/${zoneKey}?${query}`, {
      method: 'GET',
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
        'X-Client-Id': request.headers.get('x-client-id') ?? '',
      },
    });
    const json: { data?: { ad?: unknown } } = await res.json().catch(() => ({}));
    const ad = res.ok ? (json?.data?.ad ?? null) : null;
    return NextResponse.json({ ad }, { headers: NO_STORE });
  } catch {
    return NextResponse.json({ ad: null }, { headers: NO_STORE });
  }
}
