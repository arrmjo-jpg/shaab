'use client';

import { useEffect, useRef } from 'react';

import { getClientId } from './client-id';
import type { EngageableType } from './use-engagement';

// منارة المشاهدة (العميل) — تحتسب مشاهدةً واحدةً بعد مكوث قصير (تجاوز الارتداد/الـprefetch).
// **التوكن يُجلَب طازجاً** من نقطة الحالة غير المُكاشة (`GET /api/engagement/{type}/{id}` ⇒ meta.view_token):
// صفحة التفاصيل مُكاشة (ISR) وقد يتجاوز عمرُها عمرَ التوكن القصير (view_beacon.ttl) فيصبح توكن SSR
// منتهياً ويردّ الباك إند 422. ثمّ تُرسَل النبضة إلى /view. عقد الفاعل موحَّد (X-Client-Id). منع التكرار
// (لكلّ فاعل) + حدّ المعدّل في الباك إند؛ هنا نُطلق مرّة لكلّ تركيب (best-effort، لا يُعطّل العرض).
//
// `active` (افتراضي true): للأسطح المنزلقة (الريلز) تُمرَّر حالة تنشيط العنصر — فلا تُحتسب المشاهدة إلا
// بعد بقائه نشطاً مدّةَ المكوث (تمرير سريع لا يُحتسب: يُنظَّف المؤقّت عند فقد التنشيط). للصفحات المفردة
// (مقال/فيديو) تبقى true ⇒ تُطلَق مرّة واحدة عند التركيب. نفس الـHook لكلّ الأنواع بلا نسخ منطق.
export function useViewBeacon({
  type,
  id,
  active = true,
  dwellMs = 2000,
}: {
  type: EngageableType;
  id: number;
  active?: boolean;
  dwellMs?: number;
}): void {
  const fired = useRef(false);

  useEffect(() => {
    if (!active || fired.current) return;
    const timer = setTimeout(() => {
      fired.current = true;
      void (async () => {
        try {
          const cid = getClientId();
          // 1) توكن طازج (نقطة الحالة غير مُكاشة، بمفتاح type+id).
          const sres = await fetch(`/api/engagement/${type}/${id}`, { headers: { 'X-Client-Id': cid } });
          const sj = await sres.json().catch(() => null);
          const token: unknown = sj?.meta?.view_token;
          if (typeof token !== 'string' || token === '') return;
          // 2) نبضة المشاهدة.
          await fetch(`/api/engagement/${type}/${id}/view`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Client-Id': cid },
            body: JSON.stringify({ token }),
            keepalive: true,
          });
        } catch {
          /* best-effort — صمت تامّ عند الفشل */
        }
      })();
    }, dwellMs);
    return () => clearTimeout(timer);
  }, [type, id, active, dwellMs]);
}
