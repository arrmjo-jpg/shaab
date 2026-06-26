import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF forgot-password proxy → POST /api/v1/auth/forgot-password { email, recaptcha_token }.
// Returns only success/message (sends a reset link; no session is created).
export async function POST(request: Request): Promise<Response> {
  let body: { email?: string; recaptchaToken?: string | null };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ success: false, message: 'طلب غير صالح.' }, { status: 400 });
  }

  if (!env.apiBaseUrl) {
    return NextResponse.json({ success: false, message: 'الخدمة غير متاحة حالياً.' }, { status: 503 });
  }

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/auth/forgot-password`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: body.email ?? '', recaptcha_token: body.recaptchaToken ?? '' }),
    });
    const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));

    return NextResponse.json(
      { success: res.ok && data?.success !== false, message: data?.message ?? '' },
      { status: res.ok ? 200 : res.status },
    );
  } catch {
    return NextResponse.json({ success: false, message: 'تعذّر الاتصال بالخادم.' }, { status: 502 });
  }
}
