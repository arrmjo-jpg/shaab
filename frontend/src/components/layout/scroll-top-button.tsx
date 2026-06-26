'use client';

import { ChevronUp } from 'lucide-react';

// زرّ «العودة للأعلى» (client — يحتاج window). يُستعمل في شريط الفوتر السفليّ على الموبايل.
export function ScrollTopButton({ className }: { className?: string }) {
  return (
    <button
      type="button"
      aria-label="العودة للأعلى"
      onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
      className={className}
    >
      <ChevronUp className="size-5" aria-hidden />
    </button>
  );
}
