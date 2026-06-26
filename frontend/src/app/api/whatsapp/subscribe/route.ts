import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF: POST /api/whatsapp/subscribe → POST /api/v1/whatsapp/subscribe. لا توكن داخليّ
// (الحماية throttle على الباك إند). يمرّر X-Client-Id (طبقة الـlimiter لكلّ عميل).
export async function POST(request: Request): Promise<Response> {
  let body: { name?: string; phone?: string };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ success: false, message: 'طلب غير صالح.' }, { status: 400 });
  }

  if (!env.apiBaseUrl) {
    return NextResponse.json({ success: false, message: 'الخدمة غير متاحة حالياً.' }, { status: 503 });
  }

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/whatsapp/subscribe`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Client-Id': request.headers.get('x-client-id') ?? '',
      },
      body: JSON.stringify({ name: body.name ?? '', phone: body.phone ?? '' }),
    });

    const data: { success?: boolean; message?: string; errors?: unknown } = await res.json().catch(() => ({}));
    const ok = res.ok && data?.success !== false;

    return NextResponse.json(
      { success: ok, message: data?.message ?? '', errors: data?.errors ?? null },
      { status: ok ? 200 : res.status },
    );
  } catch {
    return NextResponse.json({ success: false, message: 'حدث خطأ في الاتصال، يرجى المحاولة لاحقاً.' }, { status: 502 });
  }
}
