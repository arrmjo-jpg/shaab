import 'server-only';
import { NextResponse } from 'next/server';

import { getAuthToken } from './auth';
import { env } from './env';

// مساعد BFF **مركزيّ واحد** لنظام «تابع» — يمرّر متابعة كيانات 365 (فريق/بطولة/لاعب/مباراة) للباك إند:
//   {GET}  /api/v1/follow/{type}/{id}   → الحالة { following }
//   {POST} /api/v1/follow/{type}/{id}   → تبديل المتابعة → { following }
//   {GET}  /api/v1/follows[?type=]      → قائمة «أتابعهم»
// المتابعة **تتطلّب دخولاً دائماً** (لا متابعة للزائر): الزائر على الحالة ⇒ following:false (بلا نداء)،
// وعلى التبديل/القائمة ⇒ 401 فيوجّهه العميل لـ/login?returnTo (نمط `engagement-bff`).

const TYPES = new Set(['team', 'competition', 'player', 'match']);

export function isFollowableType(type: string): boolean {
  return TYPES.has(type);
}

export async function forwardFollow(opts: {
  type: string;
  id: string;
  action: 'state' | 'toggle';
  request: Request;
}): Promise<NextResponse> {
  const { type, id, action } = opts;

  if (!isFollowableType(type)) return NextResponse.json({ error: 'bad_type' }, { status: 400 });
  if (!/^\d+$/.test(id)) return NextResponse.json({ error: 'bad_id' }, { status: 400 });
  if (!env.apiBaseUrl) return NextResponse.json({ error: 'no_api' }, { status: 502 });

  const token = await getAuthToken();
  // الزائر لا يتابع: الحالة = غير متابِع (بلا نداء)؛ التبديل ⇒ 401 ليحوّله العميل لـ/login.
  if (!token) {
    if (action === 'state') {
      return NextResponse.json({ success: true, data: { following: false } }, { status: 200 });
    }
    return NextResponse.json({ error: 'unauthenticated' }, { status: 401 });
  }

  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/follow/${type}/${id}`, {
      method: action === 'toggle' ? 'POST' : 'GET',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      cache: 'no-store',
    });
    const json = await res.json().catch(() => null);
    return NextResponse.json(json, { status: res.status });
  } catch {
    return NextResponse.json({ error: 'upstream' }, { status: 502 });
  }
}

export async function forwardFollowList(opts: { type?: string | null }): Promise<NextResponse> {
  if (!env.apiBaseUrl) return NextResponse.json({ error: 'no_api' }, { status: 502 });

  const token = await getAuthToken();
  if (!token) return NextResponse.json({ error: 'unauthenticated' }, { status: 401 });

  const qs = opts.type && isFollowableType(opts.type) ? `?type=${opts.type}` : '';
  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/follows${qs}`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      cache: 'no-store',
    });
    const json = await res.json().catch(() => null);
    return NextResponse.json(json, { status: res.status });
  } catch {
    return NextResponse.json({ error: 'upstream' }, { status: 502 });
  }
}
