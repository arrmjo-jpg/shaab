'use client';

import type { EngageableType } from '@/lib/use-engagement';
import { useViewBeacon } from '@/lib/use-view-beacon';

// جزيرة عميل غير مرئيّة **موحَّدة** لكلّ الأنواع (article/video/reel) — تُركَّب في سطح المحتوى لإطلاق
// منارة المشاهدة عبر الـHook المشترك. لا تُصيّر DOM. التوكن يُجلَب طازجاً داخل الـHook (لا SSR مُكاش).
// `active`: للأسطح المنزلقة (الريلز) تُمرَّر حالة تنشيط العنصر؛ تُهمَل للصفحات المفردة (=نشِط دائماً).
export function ViewBeacon({
  type,
  id,
  active,
}: {
  type: EngageableType;
  id: number;
  active?: boolean;
}) {
  useViewBeacon({ type, id, active });
  return null;
}
