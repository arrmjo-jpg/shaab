import Link from 'next/link';
import { Download } from 'lucide-react';

import type { EpaperIssue } from '@/lib/epaper';

import { ShareControl } from './share-control';

function formatDate(date: string | null): string | null {
  if (!date) return null;
  try {
    return new Intl.DateTimeFormat('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' }).format(new Date(date));
  } catch {
    return date;
  }
}

// جدار الأغلفة — شبكة الأعداد المنشورة (الأحدث أوّلاً). لا غلاف في المصدر بعد ⇒ بديل طباعيّ
// صادق. هوفر بسيط (رفعة + حدّ). كل بطاقة: تصفّح · تحميل · مشاركة.
export function MagazineWall({ issues }: { issues: EpaperIssue[] }) {
  if (issues.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center border border-dashed border-border bg-surface-2 px-6 py-16 text-center">
        <p className="text-sm text-muted">لا توجد أعداد مطابقة.</p>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4" dir="rtl">
      {issues.map((issue) => {
        const dateLabel = formatDate(issue.publicationDate);
        return (
          <article key={issue.id} className="group flex flex-col">
            <Link
              href={issue.readHref}
              aria-label={issue.title}
              className="relative flex aspect-[1/1.414] flex-col justify-between border border-border bg-surface-2 p-4 transition-all duration-200 group-hover:-translate-y-0.5 group-hover:border-fg group-hover:shadow-lg motion-reduce:transition-none motion-reduce:group-hover:translate-y-0"
            >
              {issue.cover ? (
                // eslint-disable-next-line @next/next/no-img-element -- غلاف العدد (أرشيف: تحميل كسول)
                <img src={issue.cover} alt={issue.title} loading="lazy" decoding="async" className="absolute inset-0 size-full object-cover" />
              ) : (
                <>
                  <span className="text-[11px] font-bold uppercase tracking-wide text-muted">عدد #{issue.issueNumber}</span>
                  <span className="line-clamp-4 text-base font-black leading-snug text-fg">{issue.title}</span>
                  <span className="text-[11px] font-medium text-muted">{dateLabel ?? ''}</span>
                </>
              )}
            </Link>

            <div className="mt-2 flex items-center justify-between gap-1">
              <span className="min-w-0 text-xs text-muted">
                #{issue.issueNumber}
                {dateLabel ? ` · ${dateLabel}` : ''}
                {issue.pageCount ? ` · ${issue.pageCount} ص` : ''}
              </span>
              <div className="flex shrink-0 items-center gap-1">
                {issue.downloadUrl ? (
                  <a
                    href={issue.downloadUrl}
                    aria-label="تحميل العدد"
                    title="تحميل"
                    className="inline-flex size-9 items-center justify-center border border-border text-fg transition hover:bg-surface-2"
                  >
                    <Download className="size-4" aria-hidden />
                  </a>
                ) : null}
                <ShareControl title={issue.title} href={issue.readHref} />
              </div>
            </div>
          </article>
        );
      })}
    </div>
  );
}
