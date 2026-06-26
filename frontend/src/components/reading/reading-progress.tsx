'use client';

import { useEffect, useState } from 'react';

import { ScrollTopButton } from '@/components/layout/scroll-top-button';

// طبقة قراءة مشتركة — شريط تقدّم القراءة العلويّ + زرّ العودة للأعلى العائم (يُعاد استخدام
// ScrollTopButton). يحسب التقدّم على مدى عنصر المحتوى المستهدَف. يحترم reduced-motion.
export function ReadingProgress({ targetId }: { targetId: string }) {
  const [progress, setProgress] = useState(0);
  const [showTop, setShowTop] = useState(false);

  useEffect(() => {
    const onScroll = () => {
      const doc = document.documentElement;
      const scrollTop = window.scrollY || doc.scrollTop;
      const el = document.getElementById(targetId);

      let pct = 0;
      if (el) {
        const start = el.offsetTop;
        const span = el.offsetHeight - window.innerHeight;
        pct = span > 0 ? ((scrollTop - start) / span) * 100 : scrollTop >= start ? 100 : 0;
      } else {
        const span = doc.scrollHeight - window.innerHeight;
        pct = span > 0 ? (scrollTop / span) * 100 : 0;
      }
      setProgress(Math.min(100, Math.max(0, pct)));
      setShowTop(scrollTop > 600);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    return () => {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onScroll);
    };
  }, [targetId]);

  return (
    <>
      <div className="fixed inset-x-0 top-0 z-50 h-0.5 print:hidden" aria-hidden>
        <div
          className="h-full bg-primary transition-[width] duration-150 ease-out motion-reduce:transition-none"
          style={{ width: `${progress}%` }}
        />
      </div>

      {showTop ? (
        <ScrollTopButton className="fixed bottom-20 end-4 z-40 inline-flex size-11 items-center justify-center rounded-full border border-border bg-surface text-fg shadow-lg transition-colors hover:bg-surface-2 motion-reduce:transition-none lg:bottom-6 print:hidden" />
      ) : null}
    </>
  );
}
