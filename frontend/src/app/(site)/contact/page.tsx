import type { Metadata } from 'next';

import { Container } from '@/components/layout/container';
import { ContactForm } from '@/components/public-forms/contact-form';
import { ContactInfoPanel } from '@/components/public-forms/contact-info-panel';
import { getRecaptchaConfig } from '@/lib/recaptcha';
import { buildMetadata } from '@/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  return buildMetadata({ title: 'اتصل بنا', path: '/contact' });
}

// صفحة «اتصل بنا» — ترويسة بشارة حمراء + شبكة: النموذج (يمين) ولوحة بيانات التواصل من
// إعدادات الموقع (يسار، لاصقة). البيانات كلّها من /site — صفر تصليب.
export default async function ContactPage() {
  const recaptcha = await getRecaptchaConfig();

  return (
    <Container className="py-10 md:py-14">
      <div className="mx-auto max-w-5xl">
        <header className="flex items-start gap-3 border-b border-border pb-4">
          <span className="mt-1 h-8 w-1 shrink-0 bg-primary" style={{ borderRadius: '9999px' }} aria-hidden />
          <div>
            <h1 className="font-heading text-2xl font-extrabold tracking-tight text-fg sm:text-3xl">اتصل بنا</h1>
            <p className="mt-1 text-sm leading-relaxed text-muted">
              يسعدنا تواصلك معنا. اختر نوع رسالتك واكتب لنا، وسنردّ عليك في أقرب وقت.
            </p>
          </div>
        </header>

        <div className="mt-8 grid items-start gap-6 lg:grid-cols-[1.6fr_1fr] lg:gap-8">
          <div className="border border-border bg-surface p-6 shadow-lg sm:p-8">
            <ContactForm recaptcha={{ enabled: recaptcha?.enabled ?? false, siteKey: recaptcha?.site_key ?? null }} />
          </div>
          <ContactInfoPanel />
        </div>
      </div>
    </Container>
  );
}
