'use client';

import { useId, useState } from 'react';

import { Button } from '@/components/ui/button';
import { useRecaptcha } from '@/hooks/use-recaptcha';
import { getClientId } from '@/lib/client-id';

// نموذج «أعلن معنا» (طلب إعلان) — مستقلّ عن اتصل بنا. useRecaptcha (action='ad_request') + X-Client-Id.
// نوع الإعلان اختيار إلزاميّ (صورة|HTML، لا نصّ حرّ) يحدّد نوع المرفق. الإرسال multipart عبر BFF.
const AD_TYPES = [
  { value: 'image', label: 'إعلان صورة' },
  { value: 'html', label: 'إعلان HTML' },
] as const;

type AdType = (typeof AD_TYPES)[number]['value'];

export function AdvertiseForm({ recaptcha }: { recaptcha: { enabled: boolean; siteKey: string | null } }) {
  const getToken = useRecaptcha(recaptcha.enabled, recaptcha.siteKey);

  const companyId = useId();
  const contactId = useId();
  const emailId = useId();
  const phoneId = useId();
  const websiteId = useId();
  const adTypeId = useId();
  const attachmentId = useId();
  const descId = useId();

  const [adType, setAdType] = useState<AdType>('image');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    const form = event.currentTarget;

    setSubmitting(true);
    try {
      let recaptchaToken: string | null = null;
      if (recaptcha.enabled) {
        recaptchaToken = await getToken('ad_request');
        if (!recaptchaToken) {
          setError('تعذّر التحقّق من reCAPTCHA، يرجى المحاولة مرّة أخرى.');
          setSubmitting(false);
          return;
        }
      }

      // multipart: FormData يلتقط كلّ الحقول المسمّاة + ملفّ المرفق. لا نضبط Content-Type
      // (المتصفّح يضبط حدّ الـmultipart). الـtoken حقلٌ في النموذج.
      const fd = new FormData(form);
      if (recaptchaToken) fd.set('recaptcha_token', recaptchaToken);

      const res = await fetch('/api/advertise', {
        method: 'POST',
        headers: { 'X-Client-Id': getClientId() },
        body: fd,
      });
      const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));

      if (!res.ok || data.success === false) {
        setError(data.message || 'تعذّر إرسال الطلب. تحقّق من البيانات المُدخلة.');
        setSubmitting(false);
        return;
      }

      form.reset();
      setAdType('image');
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
  const fileClass =
    'w-full border border-border bg-surface px-3 py-2.5 text-sm text-fg outline-none transition-colors file:me-3 file:border-0 file:bg-primary/10 file:px-3 file:py-1 file:text-primary focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30';

  if (done) {
    return (
      <div role="status" className="mt-6 border border-success/30 bg-success/10 px-4 py-4 text-sm text-success">
        تم استلام طلبك، وسيتواصل معك فريق المبيعات في أقرب وقت.
        <button
          type="button"
          onClick={() => setDone(false)}
          className="mt-2 block font-bold text-success hover:underline"
        >
          إرسال طلب آخر
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

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <label htmlFor={companyId} className="text-sm font-medium text-fg">اسم الشركة</label>
          <input id={companyId} name="company_name" type="text" required maxLength={160} className={fieldClass} />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor={contactId} className="text-sm font-medium text-fg">اسم الشخص المسؤول</label>
          <input id={contactId} name="contact_name" type="text" required maxLength={120} autoComplete="name" className={fieldClass} />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <label htmlFor={emailId} className="text-sm font-medium text-fg">البريد الإلكتروني</label>
          <input id={emailId} name="email" type="email" required dir="ltr" autoComplete="email" placeholder="you@company.com" className={`${fieldClass} text-start`} />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor={phoneId} className="text-sm font-medium text-fg">رقم الهاتف</label>
          <input id={phoneId} name="phone" type="tel" required maxLength={30} dir="ltr" autoComplete="tel" placeholder="+962…" className={`${fieldClass} text-start`} />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <label htmlFor={websiteId} className="text-sm font-medium text-fg">الموقع الإلكتروني <span className="text-muted">(اختياري)</span></label>
          <input id={websiteId} name="website" type="url" dir="ltr" maxLength={200} placeholder="https://…" className={`${fieldClass} text-start`} />
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor={adTypeId} className="text-sm font-medium text-fg">نوع الإعلان</label>
          <select
            id={adTypeId}
            name="ad_type"
            required
            value={adType}
            onChange={(e) => setAdType(e.target.value as AdType)}
            className={fieldClass}
          >
            {AD_TYPES.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </select>
        </div>
      </div>

      {/* المرفق — يتبدّل بنوع الإعلان (صورة | ZIP). الحقل يُعاد بناؤه عند تبديل النوع فيُفرَّغ. */}
      {adType === 'image' ? (
        <div className="flex flex-col gap-1.5">
          <label htmlFor={attachmentId} className="text-sm font-medium text-fg">ملف الصورة</label>
          <input
            id={attachmentId}
            name="attachment"
            type="file"
            required
            accept="image/jpeg,image/png,image/webp"
            className={fileClass}
          />
          <p className="text-caption text-muted">صيغ مقبولة: JPEG / PNG / WebP (حتى 5 ميغابايت).</p>
        </div>
      ) : (
        <div className="flex flex-col gap-1.5">
          <label htmlFor={attachmentId} className="text-sm font-medium text-fg">ملف الإعلان (ZIP)</label>
          <input
            id={attachmentId}
            name="attachment"
            type="file"
            required
            accept=".zip,application/zip"
            className={fileClass}
          />
          <p className="text-caption text-muted">
            ارفع ملف ZIP يحتوي على ملفات الإعلان (HTML/CSS/JS/Assets)، وسيتم مراجعته من قبل فريق الإدارة.
          </p>
        </div>
      )}

      <div className="flex flex-col gap-1.5">
        <label htmlFor={descId} className="text-sm font-medium text-fg">وصف الطلب</label>
        <textarea id={descId} name="description" required minLength={5} maxLength={5000} rows={6} className={areaClass} />
      </div>

      <Button type="submit" variant="primary" size="lg" disabled={submitting} aria-busy={submitting} className="rounded-none">
        {submitting ? 'جارٍ الإرسال…' : 'إرسال الطلب'}
      </Button>

      {recaptcha.enabled && <p className="text-caption text-muted">هذا الموقع محميّ بواسطة reCAPTCHA.</p>}
    </form>
  );
}
