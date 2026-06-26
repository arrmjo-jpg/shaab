import type { Metadata } from 'next';

import { LoginForm } from '@/components/auth/login-form';
import { SocialLoginPanel } from '@/components/auth/social-login-panel';
import { Container } from '@/components/layout/container';
import { getSocialAuthConfig } from '@/lib/auth-config';
import { getRecaptchaConfig } from '@/lib/recaptcha';
import { buildMetadata } from '@/lib/seo';
import { cn } from '@/lib/utils';

export async function generateMetadata(): Promise<Metadata> {
  return buildMetadata({ title: 'تسجيل الدخول', path: '/login' });
}

export default async function LoginPage() {
  const [recaptcha, providers] = await Promise.all([getRecaptchaConfig(), getSocialAuthConfig()]);
  const hasSocial = providers.length > 0;

  return (
    <Container className="py-10 md:py-16">
      <div
        className={cn(
          'mx-auto overflow-hidden border border-border bg-surface shadow-lg',
          hasSocial ? 'max-w-4xl md:grid md:grid-cols-2' : 'max-w-md',
        )}
      >
        {/* Primary panel — login form */}
        <div className="p-6 sm:p-8">
          <h1 className="font-heading text-h2 font-extrabold tracking-tight text-fg">تسجيل الدخول</h1>
          <p className="mt-2 text-sm leading-relaxed text-muted">
            سجّل دخولك للوصول إلى حسابك ومتابعة آخر الأخبار والمحتوى.
          </p>
          <LoginForm recaptcha={{ enabled: recaptcha?.enabled ?? false, siteKey: recaptcha?.site_key ?? null }} />
        </div>

        {/* Social panel — only when the backend exposes enabled providers */}
        {hasSocial && (
          <div className="border-t border-border bg-surface-2 p-6 sm:p-8 md:border-s md:border-t-0">
            <SocialLoginPanel providers={providers} />
          </div>
        )}
      </div>
    </Container>
  );
}
