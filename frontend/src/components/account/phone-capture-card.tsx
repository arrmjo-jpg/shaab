'use client';

import { useId, useState } from 'react';
import { useRouter } from 'next/navigation';

import { Button } from '@/components/ui/button';

// بطاقة «رقم الهاتف وإشعارات واتساب» الدائمة في صفحة الحساب (أسفل الترحيب). تعرض الرقم الحاليّ
// (قابل للتعديل) + حالة الاشتراك في حملات واتساب. إلغاء الاشتراك = إزالة العلامة ثمّ «حفظ»
// (الباك إند يضبط whatsapp_contacts.status=unsubscribed). تحفظ عبر الـ BFF ثمّ router.refresh().
// مستخدم جديد بلا رقم ⇒ العلامة مفعّلة افتراضيًّا (تشجيع الاشتراك)؛ من لديه رقم ⇒ تعكس حالته الفعليّة.
export function PhoneCaptureCard({
  phone,
  whatsappSubscribed,
}: {
  phone: string | null;
  whatsappSubscribed: boolean;
}) {
  const router = useRouter();
  const phoneId = useId();
  const errorId = useId();

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setDone(false);
    setSubmitting(true);

    const formData = new FormData(event.currentTarget);
    const phoneValue = String(formData.get('phone') ?? '').trim();
    const whatsappValue = formData.get('whatsapp_subscribed') === 'on';

    try {
      const res = await fetch('/api/account/phone', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone: phoneValue, whatsapp_subscribed: whatsappValue }),
      });
      const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));

      if (!res.ok || data.success === false) {
        setError(data.message || 'تعذّر حفظ البيانات، يرجى المحاولة مرّة أخرى.');
        setSubmitting(false);
        return;
      }

      setDone(true);
      setSubmitting(false);
      // إعادة جلب بيانات الخادم لتعكس البطاقة الحالة الجديدة (الرقم/الاشتراك).
      router.refresh();
    } catch {
      setError('حدث خطأ في الاتصال، يرجى المحاولة لاحقاً.');
      setSubmitting(false);
    }
  }

  return (
    <div className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
      <div>
        <h2 className="font-heading text-base font-extrabold text-fg">رقم الهاتف وإشعارات واتساب</h2>
        <p className="mt-1 text-sm text-muted">
          {phone
            ? 'يمكنك تعديل رقمك، أو إلغاء الاشتراك في حملات واتساب بإزالة العلامة ثمّ الحفظ.'
            : 'أضف رقم هاتفك لمتابعة آخر الأخبار والحملات عبر واتساب.'}
        </p>
      </div>

      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        {error && (
          <div
            id={errorId}
            role="alert"
            className="border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"
          >
            {error}
          </div>
        )}
        {done && !error && (
          <div
            role="status"
            className="border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary"
          >
            تمّ حفظ بياناتك بنجاح.
          </div>
        )}

        <div className="flex flex-col gap-1.5 sm:max-w-xs">
          <label htmlFor={phoneId} className="text-sm font-medium text-fg">
            رقم الهاتف
          </label>
          <input
            id={phoneId}
            name="phone"
            type="tel"
            required
            dir="ltr"
            autoComplete="tel"
            defaultValue={phone ?? ''}
            placeholder="+9627XXXXXXXX"
            aria-invalid={error ? true : undefined}
            aria-describedby={error ? errorId : undefined}
            className="h-11 border border-border bg-surface px-3 text-start text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30"
          />
        </div>

        <label className="flex items-start gap-2 text-sm text-fg">
          <input
            name="whatsapp_subscribed"
            type="checkbox"
            defaultChecked={phone ? whatsappSubscribed : true}
            className="mt-0.5 size-4 shrink-0 border-border text-primary focus-visible:ring-2 focus-visible:ring-primary/30"
          />
          <span>أرغب بالاشتراك في حملات وأخبار WhatsApp.</span>
        </label>

        <div>
          <Button type="submit" variant="primary" size="md" disabled={submitting} aria-busy={submitting}>
            {submitting ? 'جارٍ الحفظ…' : 'حفظ'}
          </Button>
        </div>
      </form>
    </div>
  );
}
