'use client';

import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';

import { VideoCard } from './video-card';
import type { VideoItem } from '@/lib/videos';

// رفّ فيديو أفقيّ (جزيرة عميل صغيرة) — تمرير scroll-snap لمسيّ + أسهم سطح المكتب (تظهر عند المرور، تختفي عند
// الحافات)، يحترم prefers-reduced-motion ويعمل RTL/LTR (حساب منطقيّ من حافة البداية). فارغ ⇒ null (لا تلفيق).
export function VideoRail({
  items,
  itemClassName = 'w-[80%] sm:w-[44%] md:w-[300px]',
  leadingCard,
}: {
  items: VideoItem[];
  itemClassName?: string;
  leadingCard?: ReactNode;
}) {
  const ref = useRef<HTMLUListElement>(null);
  const [canStart, setCanStart] = useState(false);
  const [canEnd, setCanEnd] = useState(false);

  const update = useCallback(() => {
    const el = ref.current;
    if (!el) return;
    const rtl = getComputedStyle(el).direction === 'rtl';
    const max = el.scrollWidth - el.clientWidth;
    const fromStart = rtl ? -el.scrollLeft : el.scrollLeft; // مسافة من حافة البداية (موحَّد للاتّجاهين)
    setCanStart(fromStart > 4);
    setCanEnd(fromStart < max - 4);
  }, []);

  useEffect(() => {
    update();
    const el = ref.current;
    if (!el) return;
    el.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    return () => {
      el.removeEventListener('scroll', update);
      window.removeEventListener('resize', update);
    };
  }, [update, items.length]);

  const scroll = (forward: boolean) => {
    const el = ref.current;
    if (!el) return;
    const rtl = getComputedStyle(el).direction === 'rtl';
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const amount = el.clientWidth * 0.85;
    el.scrollBy({ left: (rtl ? -1 : 1) * (forward ? 1 : -1) * amount, behavior: reduce ? 'auto' : 'smooth' });
  };

  if (items.length === 0 && !leadingCard) return null;

  return (
    <div className="group relative">
      <ul
        ref={ref}
        className="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {leadingCard && <li className="shrink-0 snap-start">{leadingCard}</li>}
        {items.map((v) => (
          <li key={v.id} className={`shrink-0 snap-start ${itemClassName}`}>
            <VideoCard video={v} />
          </li>
        ))}
      </ul>

      <RailArrow side="start" show={canStart} label="السابق" onClick={() => scroll(false)} />
      <RailArrow side="end" show={canEnd} label="التالي" onClick={() => scroll(true)} />
    </div>
  );
}

function RailArrow({
  side,
  show,
  label,
  onClick,
}: {
  side: 'start' | 'end';
  show: boolean;
  label: string;
  onClick: () => void;
}) {
  const Icon = side === 'start' ? ChevronLeft : ChevronRight;
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      tabIndex={-1}
      className={`absolute top-1/2 hidden size-10 -translate-y-1/2 items-center justify-center bg-surface text-fg shadow-lg ring-1 ring-border transition md:flex ${
        side === 'start' ? 'start-1' : 'end-1'
      } ${show ? 'opacity-0 group-hover:opacity-100' : 'pointer-events-none opacity-0'}`}
      style={{ borderRadius: 9999 }}
    >
      <Icon className="size-5 rtl:rotate-180" aria-hidden />
    </button>
  );
}
