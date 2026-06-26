'use client';

import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import type { FollowableType } from './use-follow';

// مزوّد متابعة **واحد** — يجلب كلّ متابعات المستخدم مرّةً (`/api/follows`) ويبنيها مجموعةً، فتقرأ منه **كلّ** نجوم
// المتابعة (رؤوس البطولات + صفوف المباريات + صفحات الكيانات) بلا جلب فرديّ (تفادي عاصفة طلبات على صفحة بعشرات
// المباريات). الزائر ⇒ 401 ⇒ مجموعة فارغة (كلّ النجوم مُفرَّغة، بلا أيّ نداء باك‑إند). التبديل يحدّث المجموعة فورًا.

const keyOf = (type: string, id: number): string => `${type}:${id}`;

interface FollowContextValue {
  ready: boolean;
  has: (type: FollowableType, id: number) => boolean;
  set: (type: FollowableType, id: number, on: boolean) => void;
}

const FollowContext = createContext<FollowContextValue | null>(null);

export function FollowProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<Set<string>>(new Set());
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const res = await fetch('/api/follows', { headers: { Accept: 'application/json' } });
        const j = res.ok ? await res.json().catch(() => null) : null;
        const follows = Array.isArray(j?.data?.follows) ? j.data.follows : [];
        if (alive) setItems(new Set(follows.map((f: { type: string; id: number }) => keyOf(f.type, f.id))));
      } catch {
        /* الزائر/الخطأ ⇒ مجموعة فارغة */
      } finally {
        if (alive) setReady(true);
      }
    })();
    return () => {
      alive = false;
    };
  }, []);

  const has = useCallback((type: FollowableType, id: number) => items.has(keyOf(type, id)), [items]);
  const set = useCallback((type: FollowableType, id: number, on: boolean) => {
    setItems((prev) => {
      const next = new Set(prev);
      const k = keyOf(type, id);
      if (on) next.add(k);
      else next.delete(k);
      return next;
    });
  }, []);

  return <FollowContext.Provider value={{ ready, has, set }}>{children}</FollowContext.Provider>;
}

export function useFollowContext(): FollowContextValue | null {
  return useContext(FollowContext);
}
