import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// BFF register proxy → POST /api/v1/auth/register { name, email, password, password_confirmation,
// recaptcha_token }. On success the backend issues a token → stored as httpOnly cookie (auto sign-in).
export async function POST(request: Request): Promise<Response> {
  let body: {
    name?: string;
    email?: string;
    password?: string;
    passwordConfirmation?: string;
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
    const res = await fetch(`${env.apiBaseUrl}/api/v1/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        name: body.name ?? '',
        email: body.email ?? '',
        password: body.password ?? '',
        password_confirmation: body.passwordConfirmation ?? '',
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
        maxAge: 60 * 60 * 24 * 30,
      });
    }

    return out;
  } catch {
    return NextResponse.json({ success: false, message: 'تعذّر الاتصال بالخادم.' }, { status: 502 });
  }
}
