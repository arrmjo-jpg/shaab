import type { Metadata } from 'next';

import { Container } from '@/components/layout/container';
import { UnsubscribeConfirm } from '@/components/public-forms/unsubscribe-confirm';
import { buildMetadata } from '@/lib/seo';

export async function generateMetadata(): Promise<Metadata> {
  return buildMetadata({ title: 'إلغاء الاشتراك', path: '/whatsapp/unsubscribe' });
}

// صفحة إلغاء الاشتراك في رسائل واتساب — تأكيد بزرّ (POST عبر BFF) بالتوكن السرّيّ من المسار.
export default async function WhatsappUnsubscribePage({
  params,
}: {
  params: Promise<{ token: string }>;
}) {
  const { token } = await params;

  return (
    <Container className="py-10 md:py-14">
      <div className="mx-auto max-w-xl">
        <header className="flex items-start gap-3 border-b border-border pb-4">
          <span className="mt-1 h-8 w-1 shrink-0 bg-primary" style={{ borderRadius: '9999px' }} aria-hidden />
          <h1 className="font-heading text-2xl font-extrabold tracking-tight text-fg sm:text-3xl">إلغاء الاشتراك</h1>
        </header>

        <div className="mt-8 border border-border bg-surface p-6 sm:p-8">
          <UnsubscribeConfirm token={token} />
        </div>
      </div>
    </Container>
  );
}
