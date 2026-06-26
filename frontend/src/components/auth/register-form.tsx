'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useId, useState } from 'react';

import { EyeIcon, EyeOffIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';
import { useRecaptcha } from '@/hooks/use-recaptcha';

export function RegisterForm({ recaptcha }: { recaptcha: { enabled: boolean; siteKey: string | null } }) {
  const router = useRouter();
  const getToken = useRecaptcha(recaptcha.enabled, recaptcha.siteKey);

  const nameId = useId();
  const emailId = useId();
  const passwordId = useId();
  const confirmId = useId();
  const errorId = useId();

  const [show, setShow] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    const fd = new FormData(event.currentTarget);
    const name = String(fd.get('name') ?? '');
    const email = String(fd.get('email') ?? '');
    const password = String(fd.get('password') ?? '');
    const confirm = String(fd.get('password_confirmation') ?? '');
    const terms = fd.get('terms') === 'on';

    if (password.length < 8) {
      setError('كلمة المرور يجب ألّا تقلّ عن 8 أحرف.');
      return;
    }
    if (password !== confirm) {
      setError('كلمتا المرور غير متطابقتين.');
      return;
    }
    if (!terms) {
      setError('يجب الموافقة على الشروط والأحكام.');
      return;
    }

    setSubmitting(true);
    try {
      let recaptchaToken: string | null = null;
      if (recaptcha.enabled) {
        recaptchaToken = await getToken('register');
        if (!recaptchaToken) {
          setError('تعذّر التحقّق من reCAPTCHA، يرجى المحاولة مرّة أخرى.');
          setSubmitting(false);
          return;
        }
      }

      const res = await fetch('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, password, passwordConfirmation: confirm, recaptchaToken }),
      });
      const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));

      if (!res.ok || data.success === false) {
        setError(data.message || 'تعذّر إنشاء الحساب. تحقّق من البيانات المُدخلة.');
        setSubmitting(false);
        return;
      }

      router.push('/account');
    } catch {
      setError('حدث خطأ في الاتصال، يرجى المحاولة لاحقاً.');
      setSubmitting(false);
    }
  }

  const fieldClass =
    'h-11 w-full border border-border bg-surface px-3 text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30';

  return (
    <form onSubmit={onSubmit} className="mt-6 flex flex-col gap-4">
      {error && (
        <div id={errorId} role="alert" className="border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      <div className="flex flex-col gap-1.5">
        <label htmlFor={nameId} className="text-sm font-medium text-fg">الاسم</label>
        <input id={nameId} name="name" type="text" required minLength={2} maxLength={100} autoComplete="name" className={fieldClass} />
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor={emailId} className="text-sm font-medium text-fg">البريد الإلكتروني</label>
        <input id={emailId} name="email" type="email" required dir="ltr" autoComplete="email" placeholder="you@example.com" className={`${fieldClass} text-start`} />
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor={passwordId} className="text-sm font-medium text-fg">كلمة المرور</label>
        <div className="relative flex items-center">
          <input id={passwordId} name="password" type={show ? 'text' : 'password'} required minLength={8} autoComplete="new-password" className={`${fieldClass} pe-11`} />
          <button
            type="button"
            onClick={() => setShow((s) => !s)}
            aria-label={show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'}
            aria-pressed={show}
            className="absolute end-0 flex h-11 w-11 items-center justify-center text-muted transition-colors hover:text-fg"
          >
            {show ? <EyeOffIcon className="size-5" aria-hidden /> : <EyeIcon className="size-5" aria-hidden />}
          </button>
        </div>
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor={confirmId} className="text-sm font-medium text-fg">تأكيد كلمة المرور</label>
        <input id={confirmId} name="password_confirmation" type={show ? 'text' : 'password'} required minLength={8} autoComplete="new-password" className={fieldClass} />
      </div>

      <label className="flex items-start gap-2 text-sm text-fg">
        <input name="terms" type="checkbox" required className="mt-0.5 size-4 border-border text-primary focus-visible:ring-2 focus-visible:ring-primary/30" />
        أوافق على الشروط والأحكام وسياسة الخصوصية.
      </label>

      <Button type="submit" variant="primary" size="lg" disabled={submitting} aria-busy={submitting} className="rounded-none">
        {submitting ? 'جارٍ إنشاء الحساب…' : 'إنشاء الحساب'}
      </Button>

      {recaptcha.enabled && <p className="text-caption text-muted">هذا الموقع محميّ بواسطة reCAPTCHA</p>}

      <p className="text-center text-sm text-muted">
        لديك حساب بالفعل؟{' '}
        <Link href="/login" className="font-medium text-primary hover:underline">تسجيل الدخول</Link>
      </p>
    </form>
  );
}
