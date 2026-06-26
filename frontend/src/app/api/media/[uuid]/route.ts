import { NextResponse } from 'next/server';

import { apiFetch } from '@/lib/auth';

// BFF: استطلاع حالة معالجة أصل الكاتب (GET /api/v1/media/{uuid}) — نفس مورد الإدارة
// (processing_status/processing/duration…). محصور بأصول الكاتب نفسه (الخادم يفرض الملكيّة).
export async function GET(_request: Request, { params }: { params: Promise<{ uuid: string }> }): Promise<Response> {
  const { uuid } = await params;
  const res = await apiFetch(`/api/v1/media/${encodeURIComponent(uuid)}`);
  if (!res) {
    return NextResponse.json({ success: false, message: 'تعذّر الاتصال بالخادم.' }, { status: 502 });
  }
  const data: { success?: boolean; message?: string; data?: Record<string, unknown> } = await res
    .json()
    .catch(() => ({}));
  return NextResponse.json(
    { success: res.ok && data?.success !== false, asset: data?.data ?? null },
    { status: res.ok ? 200 : res.status },
  );
}
