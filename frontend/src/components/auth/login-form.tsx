'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect, useId, useState } from 'react';

import { EyeIcon, EyeOffIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';

interface RecaptchaProps {
  enabled: boolean;
  siteKey: string | null;
}

type Grecaptcha = {
  ready: (cb: () => void) => void;
  execute: (siteKey: string, opts: { action: string }) => Promise<string>;
};

// Email/password login form. reCAPTCHA v3 (when enabled in Site Settings) is loaded and executed on
// submit — submission is blocked until a token is obtained. Submits via the same-origin BFF proxy.
// Never logs credentials; never persists the password.
export function LoginForm({ recaptcha }: { recaptcha: RecaptchaProps }) {
  const router = useRouter();
  const emailId = useId();
  const passwordId = useId();
  const errorId = useId();

  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Load reCAPTCHA v3 once, only when enabled.
  useEffect(() => {
    if (!recaptcha.enabled || !recaptcha.siteKey) return;
    if (document.getElementById('recaptcha-v3')) return;
    const script = document.createElement('script');
    script.id = 'recaptcha-v3';
    script.src = `https://www.google.com/recaptcha/api.js?render=${recaptcha.siteKey}`;
    script.async = true;
    document.head.appendChild(script);
  }, [recaptcha.enabled, recaptcha.siteKey]);

  async function getRecaptchaToken(): Promise<string | null> {
    if (!recaptcha.enabled || !recaptcha.siteKey) return null;
    const grecaptcha = (window as unknown as { grecaptcha?: Grecaptcha }).grecaptcha;
    if (!grecaptcha) return null;
    return new Promise((resolve) => {
      grecaptcha.ready(() => {
        grecaptcha
          .execute(recaptcha.siteKey as string, { action: 'login' })
          .then(resolve)
          .catch(() => resolve(null));
      });
    });
  }

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setSubmitting(true);

    const formData = new FormData(event.currentTarget);
    const email = String(formData.get('email') ?? '');
    const password = String(formData.get('password') ?? '');
    const remember = formData.get('remember') === 'on';

    try {
      let recaptchaToken: string | null = null;
      if (recaptcha.enabled) {
        recaptchaToken = await getRecaptchaToken();
        if (!recaptchaToken) {
          setError('تعذّر التحقّق من reCAPTCHA، يرجى المحاولة مرّة أخرى.');
          setSubmitting(false);
          return;
        }
      }

      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password, remember, recaptchaToken }),
      });
      const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));

      if (!res.ok || data.success === false) {
        setError(data.message || 'تعذّر تسجيل الدخول. تأكّد من البريد الإلكتروني وكلمة المرور.');
        setSubmitting(false);
        return;
      }

      // العودة لمسار المصدر إن وُجد (?returnTo=) — داخليّ فقط (يبدأ بـ"/" مفردة)، وإلا الحساب.
      const returnParam = new URLSearchParams(window.location.search).get('returnTo');
      const dest =
        returnParam && returnParam.startsWith('/') && !returnParam.startsWith('//')
          ? returnParam
          : '/account';
      router.push(dest);
    } catch {
      setError('حدث خطأ في الاتصال، يرجى المحاولة لاحقاً.');
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="mt-6 flex flex-col gap-4">
      {error && (
        <div
          id={errorId}
          role="alert"
          className="border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"
        >
          {error}
        </div>
      )}

      <div className="flex flex-col gap-1.5">
        <label htmlFor={emailId} className="text-sm font-medium text-fg">
          البريد الإلكتروني
        </label>
        <input
          id={emailId}
          name="email"
          type="email"
          required
          autoComplete="email"
          dir="ltr"
          placeholder="you@example.com"
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          className="h-11 border border-border bg-surface px-3 text-start text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor={passwordId} className="text-sm font-medium text-fg">
          كلمة المرور
        </label>
        <div className="relative flex items-center">
          <input
            id={passwordId}
            name="password"
            type={showPassword ? 'text' : 'password'}
            required
            autoComplete="current-password"
            aria-invalid={error ? true : undefined}
            aria-describedby={error ? errorId : undefined}
            className="h-11 w-full border border-border bg-surface px-3 pe-11 text-fg outline-none transition-colors focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30"
          />
          <button
            type="button"
            onClick={() => setShowPassword((s) => !s)}
            aria-label={showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'}
            aria-pressed={showPassword}
            className="absolute end-0 flex h-11 w-11 items-center justify-center text-muted transition-colors hover:text-fg focus-visible:outline-none focus-visible:text-fg"
          >
            {showPassword ? <EyeOffIcon className="size-5" aria-hidden /> : <EyeIcon className="size-5" aria-hidden />}
          </button>
        </div>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2">
        <label className="flex items-center gap-2 text-sm text-fg">
          <input
            name="remember"
            type="checkbox"
            className="size-4 border-border text-primary focus-visible:ring-2 focus-visible:ring-primary/30"
          />
          تذكّرني
        </label>
        <Link href="/forgot-password" className="text-sm font-medium text-primary hover:underline">
          نسيت كلمة المرور؟
        </Link>
      </div>

      <Button
        type="submit"
        variant="primary"
        size="lg"
        disabled={submitting}
        aria-busy={submitting}
        className="rounded-none"
      >
        {submitting ? 'جارٍ تسجيل الدخول…' : 'تسجيل الدخول'}
      </Button>

      {recaptcha.enabled && <p className="text-caption text-muted">هذا الموقع محميّ بواسطة reCAPTCHA</p>}

      <p className="text-center text-sm text-muted">
        ليس لديك حساب؟{' '}
        <Link href="/register" className="font-medium text-primary hover:underline">
          إنشاء حساب جديد
        </Link>
      </p>
    </form>
  );
}
