import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF login proxy: keeps the API origin server-side (no CORS, no exposed base URL) and forwards the
// public auth contract POST /api/v1/auth/login { email, password, recaptcha_token }. On success the
// issued token is stored as an httpOnly cookie (never returned to the browser).
export async function POST(request: Request): Promise<Response> {
  let body: { email?: string; password?: string; remember?: boolean; recaptchaToken?: string | null };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ success: false, message: 'طلب غير صالح.' }, { status: 400 });
  }

  if (!env.apiBaseUrl) {
    return NextResponse.json({ success: false, message: 'الخدمة غير متاحة حالياً.' }, { status: 503 });
  }

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        email: body.email ?? '',
        password: body.password ?? '',
        remember: body.remember ?? false,
        recaptcha_token: body.recaptchaToken ?? '',
      }),
    });

    const data: { success?: boolean; message?: string; errors?: unknown; data?: { token?: string } } = await res
      .json()
      .catch(() => ({}));

    const ok = res.ok && data?.success !== false;
    const out = NextResponse.json(
      { success: ok, message: data?.message ?? '', errors: data?.errors ?? null },
      { status: res.ok ? 200 : res.status },
    );

    const token = data?.data?.token;
    if (ok && token) {
      out.cookies.set('auth_token', token, {
        httpOnly: true,
        secure: env.isProd,
        sameSite: 'lax',
        path: '/',
        maxAge: body.remember ? 60 * 60 * 24 * 30 : undefined,
      });
    }

    return out;
  } catch {
    return NextResponse.json({ success: false, message: 'تعذّر الاتصال بالخادم.' }, { status: 502 });
  }
}
