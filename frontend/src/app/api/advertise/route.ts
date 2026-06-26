import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF: POST /api/advertise → POST /api/v1/ad-requests (multipart/form-data). يمرّر حقول النموذج
// + ملفّ المرفق + X-Client-Id كما هي. الحماية على المسار (throttle:public.ad-request + recaptcha:ad_request).
// لا يفكّ ضغط/يحلّل المرفق — مجرّد تمرير. يعيد ApiResponse مُسطّحاً للنموذج.
export async function POST(request: Request): Promise<Response> {
  if (!env.apiBaseUrl) {
    return NextResponse.json({ success: false, message: 'الخدمة غير متاحة حالياً.' }, { status: 503 });
  }

  let form: FormData;
  try {
    form = await request.formData();
  } catch {
    return NextResponse.json({ success: false, message: 'طلب غير صالح.' }, { status: 400 });
  }

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/ad-requests`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        // لا نضبط Content-Type — fetch يضبط حدّ الـmultipart تلقائياً.
        'X-Client-Id': request.headers.get('x-client-id') ?? '',
      },
      body: form,
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
