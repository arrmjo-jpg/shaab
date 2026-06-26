/** أدوات وقت الجريدة — دلالة الجدولة بتوقيت التطبيق المحلّي (Asia/Amman) لا UTC،
 *  اتّساقاً مع قرار المرحلة 1ب (الـ backend يفسّر published_at بتوقيت التطبيق). */

export const APP_TZ = 'Asia/Amman';

/**
 * قيمة input[type=datetime-local] ("YYYY-MM-DDTHH:mm") ⇒ "YYYY-MM-DD HH:mm:00".
 * تُرسَل دون لاحقة منطقة زمنية ليفسّرها الـ backend بتوقيت التطبيق (Asia/Amman)،
 * فتُحفَظ وتُقرأ بنفس اللحظة (ذهاب/إياب بلا فقد) — لا toISOString (التي تعطي UTC).
 */
export function toAppWallClock(local: string): string {
  if (!local) return '';
  const [d, tm = ''] = local.split('T');
  const time = tm.length === 5 ? `${tm}:00` : tm;
  return `${d} ${time}`;
}

/** ISO من الخادم ⇒ قيمة input datetime-local معروضة بتوقيت Asia/Amman (لتعبئة الجدولة). */
export function isoToAmmanLocalInput(iso: string | null): string {
  if (!iso) return '';
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: APP_TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(new Date(iso));
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';
  const hour = get('hour') === '24' ? '00' : get('hour');
  return `${get('year')}-${get('month')}-${get('day')}T${hour}:${get('minute')}`;
}

/** عرض لحظة (ISO) بتوقيت Asia/Amman — دلالة الجدولة (تفادي لبس توقيت المتصفّح). */
export function fmtAmmanDateTime(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Intl.DateTimeFormat(locale, {
    timeZone: APP_TZ,
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso));
}

/** تاريخ نشر (YYYY-MM-DD) — يُعرَض ثابتاً بلا تحويل منطقة زمنية. */
export function fmtDate(date: string | null, locale: string): string {
  if (!date) return '—';
  const [y, m, d] = date.split('-').map(Number);
  if (!y || !m || !d) return date;
  return new Intl.DateTimeFormat(locale, {
    timeZone: 'UTC',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(new Date(Date.UTC(y, m - 1, d)));
}
