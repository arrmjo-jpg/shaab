import { NextResponse } from 'next/server';

import { apiFetch } from '@/lib/auth';

// BFF writer media upload → forwards the multipart `file` (+ optional `profile`) to the
// Writer Media Ownership Layer (POST /api/v1/media) with the session bearer. The token
// stays server-side; the backend records uploaded_by and runs the SAME processing pipeline.
// Returns the created MediaAsset (id/uuid/url/thumb/processing_status…) for binding.
export async function POST(request: Request): Promise<Response> {
  const form = await request.formData();
  if (!form.get('file')) {
    return NextResponse.json({ success: false, message: 'لم يتمّ اختيار ملفّ.' }, { status: 400 });
  }

  const res = await apiFetch('/api/v1/media', { method: 'POST', body: form });
  if (!res) {
    return NextResponse.json({ success: false, message: 'تعذّر الاتصال بالخادم.' }, { status: 502 });
  }

  const data: { success?: boolean; message?: string; data?: Record<string, unknown> } = await res
    .json()
    .catch(() => ({}));

  return NextResponse.json(
    { success: res.ok && data?.success !== false, message: data?.message ?? '', asset: data?.data ?? null },
    { status: res.ok ? 201 : res.status },
  );
}
