'use client';

import { ChevronLeft, ChevronRight } from 'lucide-react';

// شريط تنقّل سفليّ للجوّال: ◀︎ الصفحة/الإجمالي ▶︎. في RTL: «التالية» تقدّم رقم الصفحة.
export function ReaderMobileBar({
  currentPage,
  numPages,
  onPrev,
  onNext,
}: {
  currentPage: number;
  numPages: number;
  onPrev: () => void;
  onNext: () => void;
}) {
  return (
    <div
      dir="rtl"
      className="flex shrink-0 items-center justify-center gap-6 border-t border-white/10 bg-[#1a1a1a] py-2 text-neutral-200"
    >
      <button
        type="button"
        onClick={onPrev}
        disabled={currentPage <= 1}
        aria-label="الصفحة السابقة"
        className="inline-flex size-9 items-center justify-center rounded-sm transition-colors hover:bg-white/10 disabled:opacity-30"
      >
        <ChevronRight className="size-5" aria-hidden />
      </button>
      <span className="min-w-16 text-center text-sm font-bold tabular-nums">
        {currentPage} / {numPages}
      </span>
      <button
        type="button"
        onClick={onNext}
        disabled={currentPage >= numPages}
        aria-label="الصفحة التالية"
        className="inline-flex size-9 items-center justify-center rounded-sm transition-colors hover:bg-white/10 disabled:opacity-30"
      >
        <ChevronLeft className="size-5" aria-hidden />
      </button>
    </div>
  );
}
