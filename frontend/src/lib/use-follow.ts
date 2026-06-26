'use client';

import { useRouter } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import { useFollowContext } from './follow-context';

// Hook «تابع» المركزيّ — يُعاد استخدامه لكلّ الكيانات (team/competition/player/match). مع مزوّد `FollowProvider`
// (صفحات /sport) يقرأ الحالة من المجموعة المُجمَّعة (نداء واحد للصفحة كلّها)؛ بدونه يجلب حالته بنفسه (أزرار مستقلّة).
// يبدّل تفاؤليّاً ويعالج 401 (تحويل لـ/login?returnTo + تراجع).
export type FollowableType = 'team' | 'competition' | 'player' | 'match';

export interface UseFollowResult {
  following: boolean | null; // null = قيد التحميل
  busy: boolean;
  toggle: () => Promise<void>;
}

export function useFollow(type: FollowableType, id: number): UseFollowResult {
  const router = useRouter();
  const ctx = useFollowContext();
  const [local, setLocal] = useState<boolean | null>(null);
  const [busy, setBusy] = useState(false);

  // بلا مزوّد فقط: اجلب الحالة ذاتيّاً (مع المزوّد، المجموعة المُجمَّعة هي المصدر).
  useEffect(() => {
    if (ctx) return;
    let alive = true;
    (async () => {
      try {
        const res = await fetch(`/api/follow/${type}/${id}`, { headers: { Accept: 'application/json' } });
        const j = await res.json().catch(() => null);
        if (alive) setLocal(!!j?.data?.following);
      } catch {
        if (alive) setLocal(false);
      }
    })();
    return () => {
      alive = false;
    };
  }, [ctx, type, id]);

  const following = ctx ? (ctx.ready ? ctx.has(type, id) : null) : local;

  const apply = useCallback(
    (on: boolean) => {
      if (ctx) ctx.set(type, id, on);
      else setLocal(on);
    },
    [ctx, type, id],
  );

  const goLogin = useCallback(() => {
    const path = typeof window !== 'undefined' ? window.location.pathname + window.location.search : '/';
    router.push(`/login?returnTo=${encodeURIComponent(path)}`);
  }, [router]);

  const toggle = useCallback(async () => {
    if (busy) return;
    setBusy(true);
    const prev = following ?? false;
    apply(!prev); // تحديث تفاؤليّ

    try {
      const res = await fetch(`/api/follow/${type}/${id}`, { method: 'POST', headers: { Accept: 'application/json' } });
      if (res.status === 401) {
        apply(prev);
        goLogin();
        return;
      }
      const j = await res.json().catch(() => null);
      if (j?.data) apply(!!j.data.following);
    } catch {
      apply(prev); // تراجع
    } finally {
      setBusy(false);
    }
  }, [busy, following, apply, type, id, goLogin]);

  return { following, busy, toggle };
}
