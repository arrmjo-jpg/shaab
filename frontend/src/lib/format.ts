// Arabic-locale formatting helpers (client + server safe).

export function formatNumber(n: number | null | undefined): string {
  // Latin digits for stats (clear + consistent in cards); dates keep Arabic locale.
  return new Intl.NumberFormat('en-US').format(Number(n ?? 0));
}

export function formatDate(iso?: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' }).format(d);
}

const RELATIVE = new Intl.RelativeTimeFormat('ar', { numeric: 'auto' });

// تدرّج وحدات الزمن (ثوانٍ → سنوات) لاختيار أكبر وحدة مناسبة.
const RELATIVE_DIVISIONS: { amount: number; unit: Intl.RelativeTimeFormatUnit }[] = [
  { amount: 60, unit: 'second' },
  { amount: 60, unit: 'minute' },
  { amount: 24, unit: 'hour' },
  { amount: 7, unit: 'day' },
  { amount: 4.34524, unit: 'week' },
  { amount: 12, unit: 'month' },
  { amount: Number.POSITIVE_INFINITY, unit: 'year' },
];

// تاريخ نسبيّ بالعربيّة («قبل ساعة»، «قبل يومين»، «قبل سنة») — صرف/مثنّى/جمع صحيح عبر Intl.
// يتجاوز عاماً ⇒ تاريخ مطلق. سالب الفرق = الماضي.
export function formatRelativeTime(iso?: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  let duration = (d.getTime() - Date.now()) / 1000;
  for (const division of RELATIVE_DIVISIONS) {
    if (Math.abs(duration) < division.amount) {
      return RELATIVE.format(Math.round(duration), division.unit);
    }
    duration /= division.amount;
  }
  return formatDate(iso);
}
