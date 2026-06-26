import 'server-only';
import { NextResponse } from 'next/server';

import { getAuthToken } from './auth';
import { env } from './env';

// مساعد BFF **مركزيّ واحد** لنظام التفاعل العامّ (Polymorphic) — يُعيد استخدامه كلّ الأنواع
// (article / reel / video / أي نوع لاحق) بلا تكرار. يمرّر التفاعل للباك إند المركزيّ:
//   {GET}    /api/v1/engagement/{type}/{id}/          → الحالة (reaction/favorited/metrics)
//   {POST/DELETE} /api/v1/engagement/{type}/{id}/react
//   {POST}   /api/v1/engagement/{type}/{id}/favorite
//   {POST}   /api/v1/engagement/{type}/{id}/view      (منارة المشاهدة — عامّة، token في الجسم)
// المصادقة **تصريحيّة لكلّ (نوع، فعل)**: ما يتطلّب دخولاً يردّ 401 للزائر (بلا توكن) فيوجّهه
// العميل لـ/login؛ وما يسمح بالزائر يمرّر هجيناً (Bearer إن وُجد + X-Client-Id للبصمة).
// القراءة (الحالة) عامّة دائماً (لا 401) — لترطيب حالة المستخدم client-side بأمان كاش.

const TYPES = new Set(['article', 'reel', 'video']);

// مَن يجب أن يكون مسجّلاً (وإلا 401 ⇒ العميل يحوّل لـ/login). الحفظ يتطلّب دخولاً لكلّ الأنواع؛
// الإعجاب يتطلّبه للفيديو/الريلز (نمطهما) ويسمح بالزائر للمقال (سلوكه الحاليّ).
const REQUIRE_AUTH: Record<string, { react: boolean; favorite: boolean }> = {
  article: { react: false, favorite: true },
  reel: { react: true, favorite: true },
  video: { react: true, favorite: true },
};

export function isEngageableType(type: string): boolean {
  return TYPES.has(type);
}

export async function forwardEngagement(opts: {
  type: string;
  id: string;
  action: 'react' | 'favorite' | 'state' | 'view';
  method: 'GET' | 'POST' | 'DELETE';
  request: Request;
  body?: string;
}): Promise<NextResponse> {
  const { type, id, action, method, request, body } = opts;

  if (!isEngageableType(type)) return NextResponse.json({ error: 'bad_type' }, { status: 400 });
  if (!/^\d+$/.test(id)) return NextResponse.json({ error: 'bad_id' }, { status: 400 });
  if (!env.apiBaseUrl) return NextResponse.json({ error: 'no_api' }, { status: 502 });

  // الحالة قراءة عامّة، والمشاهدة (view) عامّة كذلك؛ الكتابة (react/favorite) تخضع للسياسة.
  // الزائر على فعل يتطلّب دخولاً ⇒ 401.
  const requireAuth = (action === 'react' || action === 'favorite') && REQUIRE_AUTH[type][action];
  const token = await getAuthToken();
  if (requireAuth && !token) {
    return NextResponse.json({ error: 'unauthenticated' }, { status: 401 });
  }

  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;
  const cid = request.headers.get('x-client-id');
  if (cid) headers['X-Client-Id'] = cid;

  const suffix = action === 'state' ? '/' : `/${action}`;
  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/engagement/${type}/${id}${suffix}`, {
      method,
      headers,
      body,
      cache: 'no-store',
    });
    const json = await res.json().catch(() => null);
    return NextResponse.json(json, { status: res.status });
  } catch {
    return NextResponse.json({ error: 'upstream' }, { status: 502 });
  }
}
