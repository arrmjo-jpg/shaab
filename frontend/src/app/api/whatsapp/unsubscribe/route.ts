import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF: POST /api/whatsapp/unsubscribe → POST /api/v1/whatsapp/unsubscribe. إلغاء بتوكن سرّيّ.
export async function POST(request: Request): Promise<Response> {
  let body: { token?: string };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ success: false, message: 'طلب غير صالح.' }, { status: 400 });
  }

  if (!env.apiBaseUrl) {
    return NextResponse.json({ success: false, message: 'الخدمة غير متاحة حالياً.' }, { status: 503 });
  }

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/whatsapp/unsubscribe`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Client-Id': request.headers.get('x-client-id') ?? '',
      },
      body: JSON.stringify({ token: body.token ?? '' }),
    });

    const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));
    const ok = res.ok && data?.success !== false;

    return NextResponse.json({ success: ok, message: data?.message ?? '' }, { status: ok ? 200 : res.status });
  } catch {
    return NextResponse.json({ success: false, message: 'حدث خطأ في الاتصال، يرجى المحاولة لاحقاً.' }, { status: 502 });
  }
}
