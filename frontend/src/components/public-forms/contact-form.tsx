'use client';

import { useId, useState } from 'react';

import { Button } from '@/components/ui/button';
import { useRecaptcha } from '@/hooks/use-recaptcha';
import { getClientId } from '@/lib/client-id';

// نموذج «اتصل بنا» العامّ — نفس نمط register-form (useState + fetch→BFF + inline). يُعيد استخدام
// useRecaptcha (action='contact') و getClientId (X-Client-Id لطبقة الـlimiter). منع التكرار بـsubmitting.
const TYPES = [
  { value: 'inquiry', label: 'استفسار' },
  { value: 'complaint', label: 'شكوى' },
  { value: 'suggestion', label: 'اقتراح' },
  { value: 'other', label: 'أخرى' },
] as const;

export function ContactForm({ recaptcha }: { recaptcha: { enabled: boolean; siteKey: string | null } }) {
  const getToken = useRecaptcha(recaptcha.enabled, recaptcha.siteKey);

  const nameId = useId();
  const emailId = useId();
  const phoneId = useId();
  const subjectId = useId();
  const typeId = useId();
  const messageId = useId();

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    const form = event.currentTarget;
    const fd = new FormData(form);
    const payload = {
      name: String(fd.get('name') ?? ''),
      email: String(fd.get('email') ?? ''),
      phone: String(fd.get('phone') ?? ''),
      subject: String(fd.get('subject') ?? ''),
      type: String(fd.get('type') ?? ''),
      message: String(fd.get('message') ?? ''),
    };

    setSubmitting(true);
    try {
      let recaptchaToken: string | null = null;
      if (recaptcha.enabled) {
        recaptchaToken = await getToken('contact');
        if (!recaptchaToken) {
          setError('تعذّر التحقّق من reCAPTCHA، يرجى المحاولة مرّة أخرى.');
          setSubmitting(false);
          return;
        }
      }

      const res = await fetch('/api/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Client-Id': getClientId() },
        body: JSON.stringify({ ...payload, recaptchaToken }),
      });
      const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));

      if (!res.ok || data.success === false) {
        setError(data.message || 'تعذّر إرسال الرسالة. تحقّق من البيانات المُدخلة.');
        setSubmitting(false);
        return;
      }

      form.reset();
      setDone(true);
      setSubmitting(false);
    } catch {
      setError('حدث خطأ في الاتصال، يرجى المحاولة لاحقاً.');
      setSubmitting(false);
    }
  }

  const fieldClass =
    'h-11 w-full border border-border bg-surface px-3 text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30';
  const areaClass =
    'min-h-32 w-full border border-border bg-surface px-3 py-2 text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30';

  if (done) {
    return (
      <div role="status" className="mt-6 border border-success/30 bg-success/10 px-4 py-4 text-sm text-success">
        تم استلام رسالتك، وسنتواصل معك في أقرب وقت.
        <button
          type="button"
          onClick={() => setDone(false)}
          className="mt-2 block font-bold text-success hover:underline"
        >
          إرسال رسالة أخرى
        </button>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} className="mt-6 flex flex-col gap-4">
      {error && (
        <div role="alert" className="border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      <div className="flex flex-col gap-1.5">
        <label htmlFor={nameId} className="text-sm font-medium text-fg">الاسم</label>
        <input id={nameId} name="name" type="text" required minLength={2} maxLength={120} autoComplete="name" className={fieldClass} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <label htmlFor={emailId} className="text-sm font-medium text-fg">البريد الإلكتروني</label>
          <input id={emailId} name="email" type="email" required dir="ltr" autoComplete="email" placeholder="you@example.com" className={`${fieldClass} text-start`} />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor={phoneId} className="text-sm font-medium text-fg">رقم الهاتف</label>
          <input id={phoneId} name="phone" type="tel" required maxLength={30} dir="ltr" autoComplete="tel" placeholder="+962…" className={`${fieldClass} text-start`} />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <label htmlFor={subjectId} className="text-sm font-medium text-fg">الموضوع</label>
          <input id={subjectId} name="subject" type="text" required maxLength={200} className={fieldClass} />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor={typeId} className="text-sm font-medium text-fg">نوع الرسالة</label>
          <select id={typeId} name="type" required defaultValue="inquiry" className={fieldClass}>
            {TYPES.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </select>
        </div>
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor={messageId} className="text-sm font-medium text-fg">نص الرسالة</label>
        <textarea id={messageId} name="message" required minLength={5} maxLength={5000} rows={6} className={areaClass} />
      </div>

      <Button type="submit" variant="primary" size="lg" disabled={submitting} aria-busy={submitting} className="rounded-none">
        {submitting ? 'جارٍ الإرسال…' : 'إرسال الرسالة'}
      </Button>

      {recaptcha.enabled && <p className="text-caption text-muted">هذا الموقع محميّ بواسطة reCAPTCHA.</p>}
    </form>
  );
}
