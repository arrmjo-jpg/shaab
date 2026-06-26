'use client';

import { useRouter } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';

import { getClientId } from './client-id';

// Hook التفاعل **المركزيّ الوحيد** — يُعاد استخدامه لكلّ أنواع المحتوى (article/reel/video/…).
// يحتكر منطق الإعجاب/الحفظ: تحديث تفاؤليّ + toggle + معالجة 401 (تحويل لـ/login?returnTo مع
// إرجاع الحالة) + ترطيب حالة المستخدم client-side (أمان كاش — لا تُخبز في صفحة مُكاشة).
// لا يحوي أيّ منطق خاصّ بنوع؛ الأسطح تستهلكه وترسم أزرارها فقط (صفر تكرار منطق).

export type Reaction = 'like' | 'dislike' | null;
export type EngageableType = 'article' | 'reel' | 'video';

export interface EngagementMetrics {
  views: number;
  likes: number;
  dislikes: number;
  favorites: number;
}

export interface UseEngagementResult {
  metrics: EngagementMetrics;
  reaction: Reaction;
  favorited: boolean;
  busy: boolean;
  react: (next: 'like' | 'dislike') => Promise<void>;
  toggleFavorite: () => Promise<void>;
}

export function useEngagement({
  type,
  id,
  initialMetrics,
  initialReaction = null,
  initialFavorited = false,
  hydrate = false,
}: {
  type: EngageableType;
  id: number;
  initialMetrics: EngagementMetrics;
  initialReaction?: Reaction;
  initialFavorited?: boolean;
  /** ترطيب حالة المستخدم (reaction/favorited) عبر GET عند التركيب — للأسطح المُكاشة (صفحة المشاهدة). */
  hydrate?: boolean;
}): UseEngagementResult {
  const router = useRouter();
  const [metrics, setMetrics] = useState<EngagementMetrics>(initialMetrics);
  const [reaction, setReaction] = useState<Reaction>(initialReaction);
  const [favorited, setFavorited] = useState<boolean>(initialFavorited);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!hydrate) return;
    let alive = true;
    (async () => {
      try {
        const res = await fetch(`/api/engagement/${type}/${id}`, {
          headers: { 'X-Client-Id': getClientId() },
        });
        const j = await res.json().catch(() => null);
        if (!alive || !j?.data) return;
        if (j.data.metrics) setMetrics((m) => ({ ...m, ...j.data.metrics }));
        setReaction((j.data.reaction as Reaction) ?? null);
        setFavorited(!!j.data.favorited);
      } catch {
        /* تجاهل — تبقى القيم الأوّليّة (SSR) */
      }
    })();
    return () => {
      alive = false;
    };
  }, [type, id, hydrate]);

  const goLogin = useCallback(() => {
    const path =
      typeof window !== 'undefined' ? window.location.pathname + window.location.search : '/';
    router.push(`/login?returnTo=${encodeURIComponent(path)}`);
  }, [router]);

  const react = useCallback(
    async (next: 'like' | 'dislike') => {
      if (busy) return;
      setBusy(true);
      const prevReaction = reaction;
      const prevMetrics = metrics;
      const removing = prevReaction === next;

      // تحديث تفاؤليّ
      if (removing) {
        setReaction(null);
        setMetrics((m) => ({
          ...m,
          likes: next === 'like' ? Math.max(0, m.likes - 1) : m.likes,
          dislikes: next === 'dislike' ? Math.max(0, m.dislikes - 1) : m.dislikes,
        }));
      } else {
        setReaction(next);
        setMetrics((m) => {
          const n = { ...m };
          if (next === 'like') {
            n.likes += 1;
            if (prevReaction === 'dislike') n.dislikes = Math.max(0, n.dislikes - 1);
          } else {
            n.dislikes += 1;
            if (prevReaction === 'like') n.likes = Math.max(0, n.likes - 1);
          }
          return n;
        });
      }

      try {
        const res = await fetch(`/api/engagement/${type}/${id}/react`, {
          method: removing ? 'DELETE' : 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Client-Id': getClientId() },
          body: removing ? undefined : JSON.stringify({ reaction: next }),
        });
        if (res.status === 401) {
          setReaction(prevReaction);
          setMetrics(prevMetrics);
          goLogin();
          return;
        }
        const j = await res.json().catch(() => null);
        if (j?.data?.metrics) {
          setMetrics((m) => ({ ...m, ...j.data.metrics }));
          setReaction((j.data.reaction as Reaction) ?? null);
        }
      } catch {
        /* يُبقى التحديث التفاؤليّ */
      } finally {
        setBusy(false);
      }
    },
    [busy, reaction, metrics, type, id, goLogin],
  );

  const toggleFavorite = useCallback(async () => {
    if (busy) return;
    setBusy(true);
    const next = !favorited;
    const prevFavorited = favorited;
    const prevFavorites = metrics.favorites;

    setFavorited(next);
    setMetrics((m) => ({ ...m, favorites: Math.max(0, m.favorites + (next ? 1 : -1)) }));

    try {
      const res = await fetch(`/api/engagement/${type}/${id}/favorite`, {
        method: 'POST',
        headers: { 'X-Client-Id': getClientId() },
      });
      if (res.status === 401) {
        setFavorited(prevFavorited);
        setMetrics((m) => ({ ...m, favorites: prevFavorites }));
        goLogin();
        return;
      }
      const j = await res.json().catch(() => null);
      if (j?.data) {
        if (j.data.metrics) setMetrics((m) => ({ ...m, ...j.data.metrics }));
        setFavorited(!!j.data.favorited);
      }
    } catch {
      /* يُبقى التحديث التفاؤليّ */
    } finally {
      setBusy(false);
    }
  }, [busy, favorited, metrics, type, id, goLogin]);

  return { metrics, reaction, favorited, busy, react, toggleFavorite };
}
