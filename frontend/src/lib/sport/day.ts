// مساعِدات تاريخ نقيّة لفلتر أيّام /sport (لا server-only — يُستعمل في مكوّن خادميّ + طبقة البيانات).
// «اليوم» بتوقيت عمّان (مهمّ: قبل منتصف الليل UTC قد يختلف اليوم المحليّ عن UTC). الصيغة الداخليّة YYYY-MM-DD.

const TZ = 'Asia/Amman';

/** تاريخ اليوم (YYYY-MM-DD) بتوقيت عمّان. */
export function todayAmman(): string {
  // en-CA يُخرج ISO ‎YYYY-MM-DD
  return new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(
    new Date(),
  );
}

/** إزاحة يوم (YYYY-MM-DD) بعدد أيّام؛ ظهيرة UTC لتفادي مشاكل التوقيت الصيفيّ. */
export function shiftYmd(ymd: string, days: number): string {
  const [y, m, d] = ymd.split('-').map(Number);
  const dt = new Date(Date.UTC(y, m - 1, d, 12));
  dt.setUTCDate(dt.getUTCDate() + days);
  return dt.toISOString().slice(0, 10);
}

/** YYYY-MM-DD → DD/MM/YYYY (صيغة بارامتر تاريخ 365). */
export function ymdToDmy(ymd: string): string {
  const [y, m, d] = ymd.split('-');
  return `${d}/${m}/${y}`;
}

/** تحقّق صارم من الصيغة + أنّه تاريخ حقيقيّ (يمنع حقن مدخلات في الـAPI). */
export function isValidYmd(s: string | null | undefined): s is string {
  if (!s || !/^\d{4}-\d{2}-\d{2}$/.test(s)) return false;
  const [y, m, d] = s.split('-').map(Number);
  const dt = new Date(Date.UTC(y, m - 1, d, 12));
  return dt.getUTCFullYear() === y && dt.getUTCMonth() === m - 1 && dt.getUTCDate() === d;
}

/** فرق الأيّام بين تاريخين (b − a) بالأيّام التقويميّة. */
export function diffDays(a: string, b: string): number {
  const u = (s: string) => {
    const [y, m, d] = s.split('-').map(Number);
    return Date.UTC(y, m - 1, d, 12);
  };
  return Math.round((u(b) - u(a)) / 86_400_000);
}

/** وسم اليوم: أمس/اليوم/غداً وإلا اسم اليوم؛ مع تاريخ مقروء (٨ يونيو). */
export function dayParts(ymd: string, today: string): { label: string; date: string; weekday: string } {
  const diff = diffDays(today, ymd);
  const [y, m, d] = ymd.split('-').map(Number);
  const dt = new Date(Date.UTC(y, m - 1, d, 12));
  const weekday = new Intl.DateTimeFormat('ar', { weekday: 'long', timeZone: 'UTC' }).format(dt);
  const date = new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'long', timeZone: 'UTC' }).format(dt);
  const label = diff === -1 ? 'أمس' : diff === 0 ? 'اليوم' : diff === 1 ? 'غداً' : weekday;
  return { label, date, weekday };
}
