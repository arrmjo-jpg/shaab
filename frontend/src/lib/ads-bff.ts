import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// منارة تتبّع إعلان (ظهور/نقر) — تُمرَّر للباك إند. عقد Cache/CDN: cache:'no-store' على الجلب
// وعلى الاستجابة (ممنوع كاش/CDN للتتبّع). تمرير X-Client-Id (فاعل ثابت/throttle). صامدة (لا تكسر
// الصفحة) — تُعيد { ok } فقط. لا نظام كاش/Backend جديد: نمط BFF القائم نفسه (انظر engagement-bff).
const NO_STORE = { 'Cache-Control': 'no-store, no-cache, must-revalidate' } as const;

export async function forwardAdBeacon(request: Request, path: string): Promise<Response> {
  if (!env.apiBaseUrl) {
    return NextResponse.json({ ok: false }, { headers: NO_STORE });
  }

  let token = '';
  try {
    const body: { token?: unknown } = await request.json();
    token = typeof body.token === 'string' ? body.token : '';
  } catch {
    return NextResponse.json({ ok: false }, { status: 400, headers: NO_STORE });
  }
  if (token === '') {
    return NextResponse.json({ ok: false }, { status: 400, headers: NO_STORE });
  }

  try {
    const res = await fetch(`${env.apiBaseUrl}${path}`, {
      method: 'POST',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Client-Id': request.headers.get('x-client-id') ?? '',
      },
      body: JSON.stringify({ token }),
    });
    return NextResponse.json({ ok: res.ok }, { headers: NO_STORE });
  } catch {
    return NextResponse.json({ ok: false }, { status: 502, headers: NO_STORE });
  }
}
