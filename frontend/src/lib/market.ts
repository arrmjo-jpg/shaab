// حالة سوق عمّان المالي (ASE) — من جدول التداول المعلن (أحد–خميس، 10:00–13:00 بتوقيت عمّان)
// لا «اختلاق مؤشّر»: نعرض حالة مفتوح/مغلق فقط (تقدير زمنيّ). لا قيمة مؤشّر حتّى توفّر واجهة ASE حقيقيّة.
// عند توفّر واجهة مؤشّر لاحقاً: تُضاف هنا دون إعادة تصميم الواجهة.
export interface MarketStatus {
  open: boolean;
  label: string;
}

const TRADING_DAYS = new Set(['Sun', 'Mon', 'Tue', 'Wed', 'Thu']);

export function aseMarketStatus(now: Date = new Date()): MarketStatus {
  try {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Amman',
      weekday: 'short',
      hour: 'numeric',
      hour12: false,
    }).formatToParts(now);

    const weekday = parts.find((p) => p.type === 'weekday')?.value ?? '';
    let hour = Number(parts.find((p) => p.type === 'hour')?.value ?? '0');
    if (hour === 24) hour = 0;

    const open = TRADING_DAYS.has(weekday) && hour >= 10 && hour < 13;
    return { open, label: open ? 'السوق مفتوح' : 'السوق مغلق' };
  } catch {
    return { open: false, label: 'السوق مغلق' };
  }
}
