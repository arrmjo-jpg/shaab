'use client';

import { Share2 } from 'lucide-react';

// مشاركة عدد/صفحة — يعيد استخدام مشاركة المتصفّح (Web Share) أو نسخ الرابط. صفر باك إند.
export function ShareControl({ title, href, className }: { title: string; href: string; className?: string }) {
  async function onShare() {
    const url = `${window.location.origin}${href}`;
    try {
      if (navigator.share) await navigator.share({ title, url });
      else await navigator.clipboard.writeText(url);
    } catch {
      /* ألغى المستخدم أو تعذّر — تجاهل */
    }
  }

  return (
    <button
      type="button"
      onClick={() => void onShare()}
      aria-label="مشاركة"
      title="مشاركة"
      className={className ?? 'inline-flex size-9 items-center justify-center border border-border text-fg transition hover:bg-surface-2'}
    >
      <Share2 className="size-4" aria-hidden />
    </button>
  );
}
