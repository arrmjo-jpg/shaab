import { type NextRequest, NextResponse } from 'next/server';

import { getAuthToken } from '@/lib/auth';
import { env } from '@/lib/env';

// BFF إنشاء تعليق — يعيد استخدام نقطة الباك إند POST /{locale}/articles/{slug}/comments (نمط engagement-bff).
// يمرّر Bearer إن وُجد توكن (مستخدم مسجّل ⇒ الاسم/البريد من الحساب، يتجاهلهما الباك إند) وإلا يمرّر اسم/بريد الزائر.
// الباك إند يفرض البوّابة (CommentGuard) ويُنشئ pending. لا منطق تعليق جديد — تمرير فقط.
export async function POST(request: NextRequest): Promise<NextResponse> {
  if (!env.apiBaseUrl) return NextResponse.json({ error: 'no_api' }, { status: 502 });

  let payload: unknown;
  try {
    payload = await request.json();
  } catch {
    return NextResponse.json({ error: 'bad_json' }, { status: 400 });
  }

  const p = (payload ?? {}) as Record<string, unknown>;
  const slug = typeof p.slug === 'string' ? p.slug : '';
  const body = typeof p.body === 'string' ? p.body.trim() : '';
  if (!slug || body.length < 2) return NextResponse.json({ error: 'invalid' }, { status: 422 });

  const forward: Record<string, unknown> = { body };
  if (typeof p.parentId === 'number') forward.parent_id = p.parentId;
  if (typeof p.authorName === 'string' && p.authorName.trim()) forward.author_name = p.authorName.trim();
  if (typeof p.authorEmail === 'string' && p.authorEmail.trim()) forward.author_email = p.authorEmail.trim();

  const token = await getAuthToken();
  const headers: Record<string, string> = { Accept: 'application/json', 'Content-Type': 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/ar/articles/${encodeURIComponent(slug)}/comments`, {
      method: 'POST',
      headers,
      body: JSON.stringify(forward),
      cache: 'no-store',
    });
    const json: unknown = await res.json().catch(() => null);
    return NextResponse.json(json, { status: res.status });
  } catch {
    return NextResponse.json({ error: 'upstream' }, { status: 502 });
  }
}
