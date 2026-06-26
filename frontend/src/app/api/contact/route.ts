import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF: POST /api/contact → POST /api/v1/contact. لا توكن داخليّ (نريد الحماية: throttle+recaptcha
// تُطبَّق). يمرّر X-Client-Id (طبقة الـlimiter لكلّ عميل). يعيد ApiResponse مُسطّحاً للنموذج.
export async function POST(request: Request): Promise<Response> {
  let body: {
    name?: string;
    email?: string;
    phone?: string;
    subject?: string;
    type?: string;
    message?: string;
    recaptchaToken?: string | null;
  };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ success: false, message: 'طلب غير صالح.' }, { status: 400 });
  }

  if (!env.apiBaseUrl) {
    return NextResponse.json({ success: false, message: 'الخدمة غير متاحة حالياً.' }, { status: 503 });
  }

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/contact`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Client-Id': request.headers.get('x-client-id') ?? '',
      },
      body: JSON.stringify({
        name: body.name ?? '',
        email: body.email ?? '',
        phone: body.phone ?? '',
        subject: body.subject ?? '',
        type: body.type ?? '',
        message: body.message ?? '',
        recaptcha_token: body.recaptchaToken ?? '',
      }),
    });

    const data: { success?: boolean; message?: string; errors?: unknown } = await res.json().catch(() => ({}));
    const ok = res.ok && data?.success !== false;

    return NextResponse.json(
      { success: ok, message: data?.message ?? '', errors: data?.errors ?? null },
      { status: res.ok ? 200 : res.status },
    );
  } catch {
    return NextResponse.json({ success: false, message: 'تعذّر الاتصال بالخادم.' }, { status: 502 });
  }
}
