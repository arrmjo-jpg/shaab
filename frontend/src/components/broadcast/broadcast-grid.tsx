'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';

import type { BroadcastCard } from '@/lib/broadcast';

// شبكة بثّ قابلة لإعادة الاستخدام مع **عرض تدريجيّ**: تُظهر دفعةً أوّليّة ثمّ تكشف المزيد عند
// التمرير لأسفل (IntersectionObserver) **وأيضاً** بزرّ «تحميل المزيد» (الوصوليّة + من لا يمرّر).
// العناصر مُجلَبة كاملةً (حتى per_page الأعلى) ⇒ الكشف فوريّ بلا طلبات إضافيّة. ≤ الدفعة ⇒ لا زرّ.
const STEP = 8;

export function BroadcastGrid({ items }: { items: BroadcastCard[] }) {
  const [visible, setVisible] = useState(() => Math.min(STEP, items.length));
  const sentinelRef = useRef<HTMLDivElement>(null);

  const showMore = () => setVisible((v) => Math.min(v + STEP, items.length));

  useEffect(() => {
    if (visible >= items.length) return;
    const el = sentinelRef.current;
    if (!el) return;
    const io = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) {
          setVisible((v) => Math.min(v + STEP, items.length));
        }
      },
      { rootMargin: '400px' },
    );
    io.observe(el);

    return () => io.disconnect();
  }, [visible, items.length]);

  const remaining = items.length - visible;

  return (
    <>
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        {items.slice(0, visible).map((b) => (
          <Link key={b.id} href={b.href} className="group flex flex-col">
            <div className="relative aspect-video overflow-hidden border border-border bg-surface-2">
              {b.shareImage ? (
                // eslint-disable-next-line @next/next/no-img-element -- صورة البثّ (تحميل كسول)
                <img
                  src={b.shareImage}
                  alt={b.title}
                  loading="lazy"
                  decoding="async"
                  className="absolute inset-0 size-full object-cover transition-transform duration-200 group-hover:scale-105"
                />
              ) : null}
              {b.status === 'live' ? (
                <span className="absolute end-2 top-2 inline-flex items-center gap-1 bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">
                  <span className="inline-block size-1.5 animate-pulse rounded-full bg-white" aria-hidden /> مباشر
                </span>
              ) : null}
            </div>
            <span className="mt-2 line-clamp-2 text-sm font-bold text-fg group-hover:text-primary">{b.title}</span>
          </Link>
        ))}
      </div>

      {remaining > 0 ? (
        <div ref={sentinelRef} className="mt-6 flex justify-center">
          <button
            type="button"
            onClick={showMore}
            className="bg-surface-2 px-6 py-2.5 text-sm font-bold text-fg transition hover:bg-primary hover:text-white"
          >
            تحميل المزيد ({remaining.toLocaleString('ar-EG')})
          </button>
        </div>
      ) : null}
    </>
  );
}
