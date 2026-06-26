import Link from 'next/link';
import { Clock, History } from 'lucide-react';

import { AudioReader } from '@/components/reading/audio-reader';
import { ShareButtons } from '@/components/share/share-buttons';
import { readingMinutes } from '@/lib/articles';
import { formatRelativeTime } from '@/lib/format';
import type { FaqItem, StaticPageDetail } from '@/lib/static-pages';

// عارض الصفحة النصّيّة (Server) — هيرو هادئ نصّ-أوّلاً + متن. الوضع FAQ (template=faq) يعرض
// أكورديون <details> أصيلاً (إمكانيّة وصول + بلا JS)؛ غيره يعرض المتن عبر `.tiptap-content`
// (مُعقَّم خادميًّا — نفس نمط المقال). لا سطر كاتب (محتوى مؤسّسيّ). صفّ إجراءات موحّد
// (استماع + مشاركة) تحت العنوان أعلى الصفحة — نفس نمط الخبر؛ CTA أسفل.
export function StaticPageView({
  page,
  contentHtml,
  faqItems,
  shareUrl,
  ttsEnabled,
}: {
  page: StaticPageDetail;
  contentHtml: string;
  faqItems: FaqItem[];
  shareUrl: string;
  /** إظهار «الاستماع» (Gemini TTS) — قيمة قادمة من إعدادات Spatie. */
  ttsEnabled?: boolean;
}) {
  const isFaq = page.template === 'faq' && faqItems.length > 0;
  const minutes = readingMinutes(page.contentHtml);
  const updatedAbsolute = page.updatedAt
    ? new Date(page.updatedAt).toLocaleDateString('ar', { year: 'numeric', month: 'long', day: 'numeric' })
    : null;

  return (
    <article>
      {/* الهيرو */}
      <header>
        <h1 className="text-balance font-heading text-h2 font-extrabold leading-tight tracking-tight text-fg sm:text-[2.25rem] lg:text-[2.5rem] lg:leading-[1.15]">
          {page.title}
        </h1>

        {page.seo.description ? (
          <p className="mt-3 text-lg leading-relaxed text-muted">{page.seo.description}</p>
        ) : null}

        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
          {page.updatedAt ? (
            <span className="inline-flex items-center gap-1.5">
              <History className="size-4 shrink-0" aria-hidden />
              <span>آخر تحديث:</span>
              <time dateTime={page.updatedAt} title={updatedAbsolute ?? undefined} className="text-fg">
                {formatRelativeTime(page.updatedAt)}
              </time>
            </span>
          ) : null}
          {minutes > 0 ? (
            <span className="inline-flex items-center gap-1.5">
              <Clock className="size-4 shrink-0" aria-hidden />
              {minutes} دقيقة قراءة
            </span>
          ) : null}
        </div>

        {/* صفّ الإجراءات الموحّد — تحت العنوان أعلى الصفحة، نفس نمط الخبر: استماع (يسار) + مشاركة (يمين) */}
        <div className="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-y border-border py-2.5 print:hidden">
          <div className="flex flex-wrap items-center gap-3">
            {ttsEnabled ? <AudioReader targetId="page-content" /> : null}
          </div>
          <ShareButtons url={shareUrl} title={page.title} />
        </div>
      </header>

      {/* المتن */}
      <div id="page-content" className="mt-6">
        {isFaq ? (
          <div className="flex flex-col gap-3">
            {faqItems.map((item, idx) => (
              <details
                key={`${idx}-${item.question}`}
                className="group border border-border bg-surface-2/40 px-4 py-3 transition-colors open:bg-surface-2/60"
              >
                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-bold text-fg [&::-webkit-details-marker]:hidden">
                  {item.question}
                  <span
                    className="shrink-0 text-muted transition-transform group-open:rotate-180 motion-reduce:transition-none"
                    aria-hidden
                  >
                    ▾
                  </span>
                </summary>
                {item.answerHtml ? (
                  <div
                    className="tiptap-content mt-3 text-[1.0625rem] leading-loose text-fg"
                    dangerouslySetInnerHTML={{ __html: item.answerHtml }}
                  />
                ) : null}
              </details>
            ))}
          </div>
        ) : (
          <div
            className="tiptap-content text-[1.0625rem] leading-loose text-fg"
            dangerouslySetInnerHTML={{ __html: contentHtml }}
          />
        )}
      </div>

      {/* CTA بسيط مُتقشّف */}
      <div className="mt-8 border-t border-border pt-4 print:hidden">
        <p className="text-sm text-muted">
          هل لديك استفسار؟{' '}
          <Link href="/contact" className="font-bold text-primary hover:underline">
            تواصل معنا
          </Link>
        </p>
      </div>
    </article>
  );
}
