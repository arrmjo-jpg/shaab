import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';

import { AdZone } from '@/components/ads/ad-zone';
import { ArticleCard } from '@/components/articles/article-card';
import { ArticleDetailView } from '@/components/articles/article-detail';
import { CommentSection } from '@/components/articles/comments/comment-section';
import { ViewBeacon } from '@/components/engagement/view-beacon';
import { Container } from '@/components/layout/container';
import { ReadingProgress } from '@/components/reading/reading-progress';
import { ReadingSidebar } from '@/components/reading/reading-sidebar';
import { SubscribeBoxSection } from '@/components/public-forms/subscribe-box-section';
import { articleSeoToMetadata, getArticle, getLiveUpdates, type LiveUpdateItem } from '@/lib/articles';
import { getArticleMetrics } from '@/lib/engagement';
import { env } from '@/lib/env';
import { getCategoryFeed, type FeedItem } from '@/lib/feed';
import { extractHeadings } from '@/lib/reading';
import { getTtsConfig } from '@/lib/tts';

// صفحة تفاصيل المحتوى **الموحّدة** (news/live/opinion) — صفحة/Layout واحد، الفرق Conditional بـ`type`.
// إعادة استخدام نقطة التفاصيل + seo (يُصدَر كما هو) + Engagement المركزيّ + التعليقات (backend). الرابط القانونيّ
// id-slug؛ نفكّ الترميز ونقشّر `^\d+-` للسلَغ المجرّد الذي تطابقه النقطة (تتبع 301 لسلَغ قديم تلقائياً). غير
// موجود ⇒ `notFound()` = 404 حقيقيّ (لذا لا `loading.tsx` على المسار). شبكة 12: محتوى 9 + Sidebar 3 فارغة.
// ISR = سقف أمان (6 ساعات)؛ التحديث الفعليّ حدثيّ عبر article:{slug}/feed:* (ملاحظة بنيويّة:
// ودجت الشريط الجانبيّ الحيّ يعيد بناء الصفحة كسولًا مع كلّ نشر — مرغوب إخباريًّا).
export const revalidate = 21600;

// فكّ الترميز (عربيّ %D9..) ثمّ إزالة بادئة المعرّف (أوّل مقطع رقميّ فقط؛ آمن مع سلَغ يبدأ برقم).
function bareSlug(idslug: string): string {
  let s = idslug;
  try {
    s = decodeURIComponent(idslug);
  } catch {
    /* مقطع غير صالح الترميز — نُبقي الخام */
  }
  return s.replace(/^\d+-/, '');
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ idslug: string }>;
}): Promise<Metadata> {
  const { idslug } = await params;
  const article = await getArticle(bareSlug(idslug));
  if (!article) return { title: 'مقال' };
  // المحوّل يمرّر قيم seo الخلفيّة كما هي (canonical/og/twitter/hreflang)؛ احتياط الـcanonical = رابط الصفحة نفسه.
  return articleSeoToMetadata(article, `${env.siteUrl}/articles/${idslug}`);
}

export default async function ArticlePage({ params }: { params: Promise<{ idslug: string }> }) {
  const { idslug } = await params;
  const slug = bareSlug(idslug);

  const article = await getArticle(slug);
  if (!article) notFound(); // 404 حقيقيّ — قبل أيّ بثّ

  const [metrics, liveUpdates, relatedRaw, ttsConfig] = await Promise.all([
    getArticleMetrics(article.id),
    article.type === 'live' ? getLiveUpdates(slug) : Promise.resolve<LiveUpdateItem[]>([]),
    article.primaryCategory
      ? getCategoryFeed(article.primaryCategory.slug, 6)
      : Promise.resolve<FeedItem[]>([]),
    getTtsConfig(),
  ]);

  // طبقة القراءة المشتركة: حقن ids بالعناوين (للمرابط/العمق). «اقرأ أيضًا» من نفس القسم.
  const { html } = extractHeadings(article.contentHtml);
  const related = relatedRaw.filter((it) => it.href !== article.href).slice(0, 4);
  const ttsEnabled = ttsConfig?.enabled ?? false;
  const shareUrl = `${env.siteUrl}${article.href}`;

  // JSON-LD: structured_data (NewsArticle/Article) + breadcrumbs (BreadcrumbList) — **يُصدَران كما هما** من الـAPI.
  const jsonLd = [article.seo?.structured_data, article.seo?.breadcrumbs]
    .filter((x): x is object => Boolean(x) && typeof x === 'object')
    .map((obj) => JSON.stringify(obj).replace(/</g, '\\u003c'));

  return (
    <Container className="py-6 sm:py-8">
      {/* منارة المشاهدة — جزيرة عميل غير مرئيّة تجلب توكناً طازجاً (state) ثمّ ترسل نبضة المشاهدة. */}
      <ViewBeacon type="article" id={article.id} />
      <ReadingProgress targetId="article-content" />

      <nav aria-label="مسار التنقّل" className="mb-4 flex flex-wrap items-center gap-2 text-caption text-muted">
        <Link href="/" className="shrink-0 transition-colors hover:text-primary">
          الرئيسية
        </Link>
        {article.primaryCategory && (
          <>
            <span aria-hidden>/</span>
            <Link
              href={`/category/${encodeURIComponent(article.primaryCategory.slug)}`}
              className="shrink-0 transition-colors hover:text-primary"
            >
              {article.primaryCategory.name}
            </Link>
          </>
        )}
        <span aria-hidden>/</span>
        <span className="line-clamp-1 text-fg">{article.title}</span>
      </nav>

      <div className="grid gap-6 lg:grid-cols-12 lg:gap-8">
        {/* المحتوى — 9 أعمدة */}
        <main className="min-w-0 lg:col-span-8">
          <ArticleDetailView
            article={article}
            slug={slug}
            metrics={metrics}
            shareUrl={shareUrl}
            liveUpdates={liveUpdates}
            contentHtml={html}
            ttsEnabled={ttsEnabled}
          />

          {/* إعلانان أسفل الخبر مباشرة — مكدّسان عموديًّا. إعادة استخدام AdZone القائم بنسبة 100%
              (client island، جلب no-store، تتبّع ظهور/نقر عبر BFF القائم). كلّ إعلان يحمل هامشه
              العلويّ بنفسه: بلا إعلان ⇒ null (صفر DOM/مساحة فارغة، ولا أثر على ISR/CDN للمقال). */}
          <AdZone zone="aalan_asfl_alkhbr_rym_1" className="mt-8" />
          <AdZone zone="aalan_asfl_alkhbr_rym_2" className="mt-6" />

          {/* صندوق الاشتراك في واتساب — بعد المحتوى وبعد كلّ الإعلانات، قبل التعليقات. */}
          <SubscribeBoxSection />

          <CommentSection slug={slug} enabled={article.commentsEnabled} />

          {related.length > 0 ? (
            <section className="mt-10 border-t border-border pt-6" aria-labelledby="related-heading">
              <h2 id="related-heading" className="mb-4 text-lg font-extrabold text-fg">
                اقرأ أيضًا
              </h2>
              <div className="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4">
                {related.map((it) => (
                  <ArticleCard key={it.href} item={it} />
                ))}
              </div>
            </section>
          ) : null}
        </main>

        {/* Sidebar — 4 أعمدة: ودجت الأخبار المشترك (آخر الأخبار/الأكثر شيوعًا). */}
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
