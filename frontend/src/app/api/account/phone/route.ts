import { NextResponse } from 'next/server';

import { apiFetch } from '@/lib/auth';

// BFF: forwards the authenticated phone + WhatsApp opt-in to PATCH /api/v1/account/phone (Bearer from
// the httpOnly cookie stays server-side; no CORS, no exposed base URL). Mirrors the avatar BFF.
export async function PATCH(request: Request): Promise<Response> {
  let body: { phone?: string; whatsapp_subscribed?: boolean };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ success: false, message: 'طلب غير صالح.' }, { status: 400 });
  }

  const res = await apiFetch('/api/v1/account/phone', {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      phone: body.phone ?? '',
      whatsapp_subscribed: body.whatsapp_subscribed ?? false,
    }),
  });
  if (!res) {
    return NextResponse.json({ success: false, message: 'تعذّر الاتصال بالخادم.' }, { status: 502 });
  }

  const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));

  return NextResponse.json(
    { success: res.ok && data?.success !== false, message: data?.message ?? '' },
    { status: res.ok ? 200 : res.status },
  );
}
