import 'server-only';

import { getAuthToken } from './auth';
import { env } from './env';
import { getGameDetail } from './sport/games';
import { getPlayer } from './sport/player';
import { getCompetitionMeta, getTeam } from './sport/stats';

// قراءة متابعات المستخدم خادميًّا (per-user، no-store) + إثراؤها بالاسم/الشعار من **مكتبة 365 الخادميّة** المُعاد
// استخدامها (getTeam/getCompetitionMeta/getPlayer/getGameDetail) — لا نخزّن أسماء 365 محليًّا. لصفحة «أتابعهم».

export interface FollowedEntity {
  type: 'team' | 'competition' | 'player' | 'match';
  id: number;
  name: string | null;
  image: string | null;
  href: string;
}

export interface MyFollows {
  authed: boolean;
  items: FollowedEntity[];
}

export async function getMyFollows(): Promise<MyFollows> {
  const token = await getAuthToken();
  if (!token || !env.apiBaseUrl) return { authed: false, items: [] };

  let raw: { type: string; id: number }[] = [];
  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/follows`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      cache: 'no-store',
    });
    if (res.status === 401) return { authed: false, items: [] };
    const j = await res.json().catch(() => null);
    raw = Array.isArray(j?.data?.follows) ? j.data.follows : [];
  } catch {
    return { authed: true, items: [] };
  }

  const items = await Promise.all(raw.map((f) => resolve(f.type, f.id)));
  return { authed: true, items: items.filter((x): x is FollowedEntity => x !== null) };
}

async function resolve(type: string, id: number): Promise<FollowedEntity | null> {
  try {
    if (type === 'team') {
      const t = await getTeam(id);
      return t ? { type: 'team', id, name: t.name, image: t.logo, href: `/sport/team/${id}` } : null;
    }
    if (type === 'competition') {
      const c = await getCompetitionMeta(id);
      return c ? { type: 'competition', id, name: c.name, image: c.logo, href: `/sport/competition/${id}` } : null;
    }
    if (type === 'player') {
      const p = await getPlayer(id);
      return p ? { type: 'player', id, name: p.name, image: p.photo, href: `/sport/player/${id}` } : null;
    }
    if (type === 'match') {
      const g = await getGameDetail(id);
      return g ? { type: 'match', id, name: `${g.home.name} ضد ${g.away.name}`, image: null, href: `/sport/match/${id}` } : null;
    }
  } catch {
    /* كيان غير قابل للحلّ من 365 ⇒ يُسقَط (لا تلفيق) */
  }
  return null;
}
