'use client';

import Link from 'next/link';
import { useId, useState } from 'react';

import { Button } from '@/components/ui/button';
import { useRecaptcha } from '@/hooks/use-recaptcha';

export function ForgotForm({ recaptcha }: { recaptcha: { enabled: boolean; siteKey: string | null } }) {
  const getToken = useRecaptcha(recaptcha.enabled, recaptcha.siteKey);
  const emailId = useId();
  const errorId = useId();

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    const email = String(new FormData(event.currentTarget).get('email') ?? '');

    setSubmitting(true);
    try {
      let recaptchaToken: string | null = null;
      if (recaptcha.enabled) {
        recaptchaToken = await getToken('forgot_password');
        if (!recaptchaToken) {
          setError('تعذّر التحقّق من reCAPTCHA، يرجى المحاولة مرّة أخرى.');
          setSubmitting(false);
          return;
        }
      }

      const res = await fetch('/api/auth/forgot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, recaptchaToken }),
      });
      const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));
      setSubmitting(false);

      if (!res.ok || data.success === false) {
        setError(data.message || 'تعذّر إرسال رابط الاستعادة.');
        return;
      }
      setDone(true);
    } catch {
      setError('حدث خطأ في الاتصال، يرجى المحاولة لاحقاً.');
      setSubmitting(false);
    }
  }

  if (done) {
    return (
      <div role="status" className="mt-6 border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
        إن كان البريد مسجّلاً لدينا، فقد أرسلنا إليه رابط استعادة كلمة المرور. يُرجى التحقّق من بريدك.
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} className="mt-6 flex flex-col gap-4">
      {error && (
        <div id={errorId} role="alert" className="border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      <div className="flex flex-col gap-1.5">
        <label htmlFor={emailId} className="text-sm font-medium text-fg">البريد الإلكتروني</label>
        <input
          id={emailId}
          name="email"
          type="email"
          required
          dir="ltr"
          autoComplete="email"
          placeholder="you@example.com"
          className="h-11 w-full border border-border bg-surface px-3 text-start text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30"
        />
      </div>

      <Button type="submit" variant="primary" size="lg" disabled={submitting} aria-busy={submitting} className="rounded-none">
        {submitting ? 'جارٍ الإرسال…' : 'إرسال رابط الاستعادة'}
      </Button>

      {recaptcha.enabled && <p className="text-caption text-muted">هذا الموقع محميّ بواسطة reCAPTCHA</p>}

      <p className="text-center text-sm text-muted">
        تذكّرت كلمة المرور؟{' '}
        <Link href="/login" className="font-medium text-primary hover:underline">تسجيل الدخول</Link>
      </p>
    </form>
  );
}
