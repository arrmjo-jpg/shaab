import type { BroadcastLifecycleAction, BroadcastStatus } from '@/types/broadcast.types';

/**
 * انتقالات دورة الحياة المسموحة لكلّ حالة (مرآة آلة الحالة الخلفية):
 *   draft    → schedule, archive
 *   scheduled→ start, fail, archive
 *   live     → offline, end, fail
 *   offline  → resume, end, fail
 *   failed   → archive
 *   ended    → archive
 *   archived → (نهائيّة)
 * تُستخدم لإظهار إجراءات صالحة فقط في القائمة/النموذج (واجهة واعية بالحالة).
 */
export const LIFECYCLE_TRANSITIONS: Record<BroadcastStatus, BroadcastLifecycleAction[]> = {
  draft: ['schedule', 'archive'],
  scheduled: ['start', 'fail', 'archive'],
  live: ['offline', 'end', 'fail'],
  offline: ['resume', 'end', 'fail'],
  failed: ['archive'],
  ended: ['archive'],
  archived: [],
};

/** الإجراءات التي تتطلّب جسماً إضافياً (نافذة بدل تنفيذ مباشر). */
export const LIFECYCLE_NEEDS_BODY: Partial<Record<BroadcastLifecycleAction, true>> = {
  schedule: true,
  fail: true,
};
