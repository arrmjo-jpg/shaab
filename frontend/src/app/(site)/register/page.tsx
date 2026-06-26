import type { Metadata } from 'next';

import { RegisterForm } from '@/components/auth/register-form';
import { Container } from '@/components/layout/container';
import { getRecaptchaConfig } from '@/lib/recaptcha';
import { buildMetadata } from '@/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  return buildMetadata({ title: 'إنشاء حساب', path: '/register' });
}

export default async function RegisterPage() {
  const recaptcha = await getRecaptchaConfig();

  return (
    <Container className="py-10 md:py-16">
      <div className="mx-auto max-w-md border border-border bg-surface p-6 shadow-lg sm:p-8">
        <h1 className="font-heading text-h2 font-extrabold tracking-tight text-fg">إنشاء حساب جديد</h1>
        <p className="mt-2 text-sm leading-relaxed text-muted">
          انضمّ إلى المنصّة لمتابعة المحتوى وحفظه والتفاعل معه.
        </p>
        <RegisterForm recaptcha={{ enabled: recaptcha?.enabled ?? false, siteKey: recaptcha?.site_key ?? null }} />
      </div>
    </Container>
  );
}
