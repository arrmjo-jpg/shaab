import { NextResponse } from 'next/server';

import { apiFetch } from '@/lib/auth';

// BFF avatar upload → forwards the multipart file to POST /api/v1/auth/avatar with the session
// bearer (no CORS, token stays server-side). Returns success + the new avatar URL.
export async function POST(request: Request): Promise<Response> {
  const form = await request.formData();
  if (!form.get('avatar')) {
    return NextResponse.json({ success: false, message: 'لم يتمّ اختيار صورة.' }, { status: 400 });
  }

  const res = await apiFetch('/api/v1/auth/avatar', { method: 'POST', body: form });
  if (!res) {
    return NextResponse.json({ success: false, message: 'تعذّر الاتصال بالخادم.' }, { status: 502 });
  }

  const data: { success?: boolean; message?: string; data?: { avatar?: string | null } } = await res
    .json()
    .catch(() => ({}));

  return NextResponse.json(
    {
      success: res.ok && data?.success !== false,
      message: data?.message ?? '',
      avatar: data?.data?.avatar ?? null,
    },
    { status: res.ok ? 200 : res.status },
  );
}
