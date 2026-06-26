'use client';

import { useState } from 'react';
import Link from 'next/link';

import type { FeedItem } from '@/lib/feed';
import { formatRelativeTime } from '@/lib/format';

// تبويبات أخبار الشريط الجانبيّ (client) — «آخر الأخبار» / «الأكثر شيوعًا». تبديل لحظيّ بلا جلب
// إضافيّ (القائمتان مُمرَّرتان خادميًّا). صفّ مُدمج: صورة مصغّرة + عنوان + تاريخ نسبيّ، رابط متراكب.
export function NewsTabs({ latest, popular }: { latest: FeedItem[]; popular: FeedItem[] }) {
  const [tab, setTab] = useState<'latest' | 'popular'>('latest');
  const items = tab === 'latest' ? latest : popular;

  const tabCls = (active: boolean) =>
    `flex-1 border-b-2 px-2 py-2.5 text-sm font-bold transition-colors ${
      active ? 'border-primary text-fg' : 'border-transparent text-muted hover:text-fg'
    }`;

  return (
    <div className="border border-border bg-surface">
      <div className="flex border-b border-border" role="tablist">
        <button
          type="button"
          role="tab"
          aria-selected={tab === 'latest'}
          onClick={() => setTab('latest')}
          className={tabCls(tab === 'latest')}
        >
          آخر الأخبار
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={tab === 'popular'}
          onClick={() => setTab('popular')}
          className={tabCls(tab === 'popular')}
        >
          الأكثر شيوعًا
        </button>
      </div>

      {items.length === 0 ? (
        <p className="px-3 py-6 text-center text-sm text-muted">لا يوجد محتوى بعد.</p>
      ) : (
        <ol className="divide-y divide-border">
          {items.map((item) => (
            <li key={item.id}>
              <Link href={item.href} className="group flex gap-3 p-3 transition-colors hover:bg-surface-2">
                <div className="aspect-[4/3] w-16 shrink-0 overflow-hidden bg-surface-2">
                  {item.image ? (
                    // eslint-disable-next-line @next/next/no-img-element -- <img> مقصود (سياسة صور الواجهة)
                    <img
                      src={item.image}
                      alt={item.imageAlt}
                      loading="lazy"
                      decoding="async"
                      className="size-full object-cover"
                    />
                  ) : (
                    <div className="size-full bg-surface-3" aria-hidden />
                  )}
                </div>
                <div className="min-w-0">
                  <h4 className="line-clamp-2 text-sm font-bold leading-snug text-fg transition-colors group-hover:text-primary">
                    {item.title}
                  </h4>
                  {item.publishedAt && (
                    <time dateTime={item.publishedAt} className="mt-1 block text-caption text-muted">
                      {formatRelativeTime(item.publishedAt)}
                    </time>
                  )}
                </div>
              </Link>
            </li>
          ))}
        </ol>
      )}
    </div>
  );
}
