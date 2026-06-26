import 'server-only';
import { cache } from 'react';
import { z } from 'zod';

import { env } from './env';

// عدّادات التفاعل (عامّة) للعرض الأوّليّ SSR — من عقد الحالة الموحّد
// GET /api/v1/engagement/{type}/{id}/ → { metrics: { views, likes, dislikes, favorites }, … }.
export interface EngagementMetrics {
  views: number;
  likes: number;
  dislikes: number;
  favorites: number;
}

const ZERO: EngagementMetrics = { views: 0, likes: 0, dislikes: 0, favorites: 0 };

const StateEnvelope = z
  .object({
    data: z
      .object({
        metrics: z
          .object({
            views: z.number().nullish(),
            likes: z.number().nullish(),
            dislikes: z.number().nullish(),
            favorites: z.number().nullish(),
          })
          .nullish(),
      })
      .passthrough()
      .nullish(),
  })
  .passthrough();

// قراءة عامّة (بلا مصادقة) — الفاعل المجهول يكفي للعدّادات العالميّة. ISR 300s؛ فشل ⇒ أصفار (لا تلفيق).
export const getArticleMetrics = cache(async (id: number): Promise<EngagementMetrics> => {
  if (!env.apiBaseUrl) return ZERO;
  try {
    const res = await fetch(`${env.apiBaseUrl}/api/v1/engagement/article/${id}/`, {
      next: { revalidate: 300, tags: ['engagement', `engagement:article:${id}`] },
    });
    if (!res.ok) return ZERO;
    const parsed = StateEnvelope.safeParse(await res.json());
    const m = parsed.success ? parsed.data.data?.metrics : null;
    return {
      views: m?.views ?? 0,
      likes: m?.likes ?? 0,
      dislikes: m?.dislikes ?? 0,
      favorites: m?.favorites ?? 0,
    };
  } catch {
    return ZERO;
  }
});
