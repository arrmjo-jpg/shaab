import type { Metadata } from 'next';

import { BroadcastLiveSection } from '@/components/broadcast/broadcast-live-section';
import { Container } from '@/components/layout/container';
import { buildMetadata } from '@/lib/seo';

// صفحة «البث المباشر» — الوجهة المستقلّة (لا شيء في الرئيسية، مدخلها شريط الخدمات تحت الناف بار).
// تعرض البثّ الحيّ الآن + أقرب مجدوَل (عدّاد) + القنوات/المحطّات (حالة فارغة صادقة). ISR قصير.
export const revalidate = 30;

export async function generateMetadata(): Promise<Metadata> {
  return buildMetadata({ title: 'البث المباشر', path: '/live' });
}

export default function LivePage() {
  return (
    <Container className="py-8 md:py-10">
      <header className="mb-6" dir="rtl">
        <h1 className="font-heading text-3xl font-extrabold tracking-tight text-fg sm:text-4xl">البث المباشر</h1>
        <p className="mt-2 text-sm text-muted">البثوث الحيّة والمجدوَلة والقنوات.</p>
      </header>
      <BroadcastLiveSection />
    </Container>
  );
}
