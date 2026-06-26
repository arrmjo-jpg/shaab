import { Clock, Eye } from 'lucide-react';
import Link from 'next/link';

import { EngagementBar } from '@/components/engagement/engagement-bar';
import { AudioReader } from '@/components/reading/audio-reader';
import { ShareButtons } from '@/components/share/share-buttons';
import { readingMinutes, type ArticleDetail, type LiveUpdateItem } from '@/lib/articles';
import type { EngagementMetrics } from '@/lib/engagement';
import { formatNumber, formatRelativeTime } from '@/lib/format';

import { ArticleLiveUpdates } from './article-live-updates';

// عارض تفاصيل المحتوى **الموحّد** — صفحة/Layout/Components واحدة، الفروق **Conditional** حسب `type`:
//  • news : غلاف 16:9 + شارات أعلام (عاجل/مميّز) + تصنيف أساسيّ/ثانويّ + سطر كاتب.
//  • live : شارة «مباشر» + الخطّ الزمنيّ للتغطية الحيّة (ArticleLiveUpdates) قبل المتن.
//  • opinion: تمركز حول الكاتب (صورة الكاتب + اسمه + نبذته) بدل غلاف 16:9 + شارة «رأي».
// المتن `content_html` (مُعقَّم خادميّاً) عبر `.tiptap-content`. SEO/Engagement/Share/Comments أنظمة مشتركة.
export function ArticleDetailView({
  article,
  slug,
  metrics,
  shareUrl,
  liveUpdates,
  contentHtml,
  ttsEnabled,
}: {
  article: ArticleDetail;
  slug: string;
  metrics: EngagementMetrics;
  shareUrl: string;
  liveUpdates: LiveUpdateItem[];
  /** المتن مع ids مُحقَّنة بالعناوين (طبقة القراءة المشتركة)؛ احتياطاً المتن الخام. */
  contentHtml?: string;
  /** إظهار «استمع للمقال» (Gemini TTS) — قيمة قادمة من إعدادات Spatie. */
  ttsEnabled?: boolean;
}) {
  const isOpinion = article.type === 'opinion';
  const isLive = article.type === 'live';
  const minutes = readingMinutes(article.contentHtml);
  const writerHref = article.author?.isWriter && article.author.id ? `/writer/${article.author.id}` : null;

  // صورة الهيرو حسب النوع: الخبر/المقال = صورة المحتوى؛ الرأي بلا صورة محتوى ⇒ صورة الكاتب؛
  // وإلا Placeholder الموقع (للخبر/الرأي). التغطية الحيّة لا Placeholder (وسائطها وتحديثاتها تكفي).
  const heroSrc = article.cover?.url ?? (isOpinion ? article.author?.avatar ?? null : null);
  const usingAvatarHero = !article.cover && heroSrc !== null;
  const heroAlt = article.cover?.alt ?? (usingAvatarHero ? article.author?.name ?? article.title : article.title);
  const showPlaceholder = heroSrc === null && !isLive;

  return (
    <article>
      {/* الترويسة: التصنيف + شارة النوع/الأعلام */}
      <div className="flex flex-wrap items-center gap-2">
        {article.primaryCategory && (
          <Link
            href={`/category/${encodeURIComponent(article.primaryCategory.slug)}`}
            className="text-sm font-extrabold text-primary hover:underline"
          >
            {article.primaryCategory.name}
          </Link>
        )}
        {isLive && (
          <span className="inline-flex items-center gap-1.5 bg-primary px-2 py-0.5 text-xs font-bold text-primary-foreground">
            {article.isLive && <span className="avatar size-2 animate-pulse bg-white" aria-hidden />}
            {article.isLive ? 'مباشر الآن' : 'تغطية حيّة'}
          </span>
        )}
        {article.flags.breaking && (
          <span className="bg-primary px-2 py-0.5 text-xs font-bold text-primary-foreground">عاجل</span>
        )}
        {isOpinion && <span className="bg-surface-2 px-2 py-0.5 text-xs font-bold text-fg">رأي</span>}
      </div>

      {/* العنوان */}
      <h1 className="mt-2.5 text-2xl font-extrabold leading-tight text-fg sm:text-3xl lg:text-[2.5rem] lg:leading-[1.15]">
        {article.title}
      </h1>

      {/* المقدّمة */}
      {article.subtitle && <p className="mt-3 text-lg leading-relaxed text-muted">{article.subtitle}</p>}

      {/* بطاقة الكاتب — للمقال (رأي) فقط: صورة + اسم + نبذة. تظهر دائمًا للمقال. */}
      {isOpinion && article.author && (
        <div className="mt-5 flex items-center gap-4 bg-surface-2 p-4">
          {article.author.avatar && (
            // eslint-disable-next-line @next/next/no-img-element -- <img> مقصود (سياسة صور الواجهة)
            <img
              src={article.author.avatar}
              alt={article.author.name}
              className="avatar size-16 shrink-0 object-cover"
              loading="eager"
            />
          )}
          <div className="min-w-0">
            {writerHref ? (
              <Link href={writerHref} className="font-bold text-fg hover:text-primary">
                {article.author.name}
              </Link>
            ) : (
              <p className="font-bold text-fg">{article.author.name}</p>
            )}
            {article.author.bio && <p className="mt-1 line-clamp-2 text-sm text-muted">{article.author.bio}</p>}
          </div>
        </div>
      )}

      {/* سطر المعلومات: التاريخ + وقت القراءة + المشاهدات — بلا اسم كاتب (الكاتب في بطاقته للمقال فقط) */}
      <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-y border-border py-3 text-sm text-muted">
        {article.publishedAt && (
          <span className="inline-flex items-center gap-1">
            <Clock className="size-4 shrink-0" aria-hidden />
            <time dateTime={article.publishedAt}>{formatRelativeTime(article.publishedAt)}</time>
          </span>
        )}
        {minutes > 0 && <span>{minutes} دقيقة قراءة</span>}
        {article.viewsCount > 0 && (
          <span className="inline-flex items-center gap-1 tabular-nums">
            <Eye className="size-4 shrink-0" aria-hidden />
            {formatNumber(article.viewsCount)}
            <span className="sr-only">مشاهدة</span>
          </span>
        )}
      </div>

      {/* صورة الهيرو — صورة المحتوى، أو صورة الكاتب (للمقال بلا صورة محتوى)، أو Placeholder الموقع. */}
      {heroSrc ? (
        <figure className="mt-5">
          {/* eslint-disable-next-line @next/next/no-img-element -- <img> مقصود (سياسة صور الواجهة) */}
          <img
            src={heroSrc}
            alt={heroAlt}
            className="aspect-[16/9] w-full bg-surface-2 object-cover"
            loading="eager"
            fetchPriority="high"
          />
          {article.cover?.alt && (
            <figcaption className="mt-2 text-caption text-muted">{article.cover.alt}</figcaption>
          )}
        </figure>
      ) : showPlaceholder ? (
        <div className="mt-5 aspect-[16/9] w-full bg-surface-3" aria-hidden />
      ) : null}

      {/* شريط الإجراءات أسفل الصورة مباشرة — استماع + تفاعل + مشاركة في سطر واحد */}
      <div className="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-y border-border py-2.5 print:hidden">
        <div className="flex flex-wrap items-center gap-3">
          {ttsEnabled ? <AudioReader targetId="article-content" /> : null}
          <EngagementBar
            type="article"
            id={article.id}
            href={article.href}
            title={article.title}
            initialMetrics={metrics}
            reactionStyle="thumbs"
            hydrate
            showShare={false}
          />
        </div>
        <ShareButtons url={shareUrl} title={article.title} />
      </div>

      {/* الخطّ الزمنيّ للتغطية الحيّة (type=live) — قبل المتن */}
      {isLive && liveUpdates.length > 0 && (
        <div className="mt-6">
          <ArticleLiveUpdates slug={slug} initial={liveUpdates} />
        </div>
      )}

      {/* المتن */}
      {(contentHtml ?? article.contentHtml) && (
        <div
          id="article-content"
          className="tiptap-content mt-6 text-[1.0625rem] leading-loose text-fg"
          dangerouslySetInnerHTML={{ __html: contentHtml ?? article.contentHtml }}
        />
      )}

      {/* الوسوم */}
      {article.tags.length > 0 && (
        <div className="mt-6 flex flex-wrap gap-2">
          {article.tags.map((t) => (
            <span key={t} className="bg-surface-2 px-2.5 py-1 text-xs text-muted">
              #{t}
            </span>
          ))}
        </div>
      )}

    </article>
  );
}
