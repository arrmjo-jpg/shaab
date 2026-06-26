import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';

import { Container } from '@/components/layout/container';
import { StaticPageView } from '@/components/pages/static-page-view';
import { ReadingProgress } from '@/components/reading/reading-progress';
import { ReadingSidebar } from '@/components/reading/reading-sidebar';
import { env } from '@/lib/env';
import { extractHeadings } from '@/lib/reading';
import { buildMetadata } from '@/lib/seo';
import { getStaticPage, splitFaq } from '@/lib/static-pages';
import { getTtsConfig } from '@/lib/tts';

// صفحة قراءة المحتوى النصّيّ (من نحن/الخصوصية/الشروط/الأسئلة الشائعة…) — تعيد استخدام نقطة
// GET /{locale}/pages/{slug} القائمة (منشورة فقط + تتبع 301 لسلَغ قديم عبر fetch). الرابط بلا
// بادئة لغة. غير موجودة ⇒ notFound() = 404 حقيقيّ. صفر API/CMS جديد.
// ISR = سقف أمان (يوم)؛ التحديث الفعليّ حدثيّ عبر page:{locale}:{slug} عند تعديل الصفحة.
export const revalidate = 86400;

function decodeSlug(slug: string): string {
  try {
    return decodeURIComponent(slug);
  } catch {
    return slug;
  }
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const page = await getStaticPage(decodeSlug(slug));
  if (!page) return { title: 'صفحة غير موجودة' };

  const base = await buildMetadata({
    title: page.seo.title || page.title,
    description: page.seo.description || undefined,
    path: page.seo.canonicalUrl || page.href,
    keywords: page.seo.keywords
      ? page.seo.keywords.split(',').map((s) => s.trim()).filter(Boolean)
      : undefined,
    type: 'article',
  });

  // تجاوز robots لكلّ صفحة (buildMetadata بيئيّ فقط) — في الإنتاج وعند ضبط الحقل صراحةً.
  if (env.isProd && page.seo.robots) {
    return { ...base, robots: page.seo.robots };
  }
  return base;
}

export default async function StaticContentPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const page = await getStaticPage(decodeSlug(slug));
  if (!page) notFound(); // 404 حقيقيّ — قبل أيّ بثّ

  const isFaq = page.template === 'faq';
  const { html } = extractHeadings(page.contentHtml);
  const faqItems = isFaq ? splitFaq(page.contentHtml) : [];
  const shareUrl = `${env.siteUrl}${page.href}`;
  const ttsEnabled = (await getTtsConfig())?.enabled ?? false;

  // JSON-LD — نمط الفيديو/المقال القائم (مبنيّ أمام-طرفيًّا، بثّ <script>). FAQPage للأسئلة.
  const primary =
    isFaq && faqItems.length > 0
      ? {
          '@context': 'https://schema.org',
          '@type': 'FAQPage',
          mainEntity: faqItems.map((it) => ({
            '@type': 'Question',
            name: it.question,
            acceptedAnswer: {
              '@type': 'Answer',
              text: it.answerHtml.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(),
            },
          })),
        }
      : {
          '@context': 'https://schema.org',
          '@type': 'WebPage',
          name: page.title,
          description: page.seo.description ?? undefined,
          url: shareUrl,
          inLanguage: page.locale,
          dateModified: page.updatedAt ?? undefined,
        };
  const breadcrumb = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      { '@type': 'ListItem', position: 1, name: 'الرئيسية', item: `${env.siteUrl}/` },
      { '@type': 'ListItem', position: 2, name: page.title, item: shareUrl },
    ],
  };
  const jsonLd = [primary, breadcrumb].map((o) => JSON.stringify(o).replace(/</g, '\\u003c'));

  return (
    <Container className="py-6 sm:py-8">
      <ReadingProgress targetId="page-content" />

      <nav
        aria-label="مسار التنقّل"
        className="mb-4 flex flex-wrap items-center gap-2 text-caption text-muted print:hidden"
      >
        <Link href="/" className="shrink-0 transition-colors hover:text-primary">
          الرئيسية
        </Link>
        <span aria-hidden>/</span>
        <span className="line-clamp-1 text-fg">{page.title}</span>
      </nav>

      {/* نفس تخطيط المقال: شبكة 12 (محتوى 8 + جانب 4). الجانب الأيسر = ودجت الأخبار المشترك. */}
      <div className="grid gap-6 lg:grid-cols-12 lg:gap-8">
        <main className="min-w-0 lg:col-span-8">
          <StaticPageView page={page} contentHtml={html} faqItems={faqItems} shareUrl={shareUrl} ttsEnabled={ttsEnabled} />
        </main>
        <aside className="hidden lg:col-span-4 lg:block print:hidden">
          <ReadingSidebar />
        </aside>
      </div>

      {jsonLd.map((j, i) => (
        <script key={i} type="application/ld+json" dangerouslySetInnerHTML={{ __html: j }} />
      ))}
    </Container>
  );
}
