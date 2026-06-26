// ⚠️ ملفّ توافق مؤقّت (TEMP shim) — لا يحوي منطقاً.
// المكوّن الفعليّ موحَّد في components/engagement/view-beacon.tsx (يخدم article/video/reel).
// أُعيد إنشاء هذا المسار كإعادة تصدير فقط لأنّ كاش webpack الدائم لخادم التطوير القائم ظلّ يطلب
// المسار القديم بعد نقله (لا يستورده أيّ ملفّ مصدر — مؤكَّد بـgrep).
// 🔴 احذفه بعد إعادة تشغيل نظيفة واحدة: أوقف :3000 → احذف .next → أعد التشغيل.
export { ViewBeacon } from '@/components/engagement/view-beacon';
