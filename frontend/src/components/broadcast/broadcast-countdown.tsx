'use client';

import { useEffect, useState } from 'react';

// عدّاد تنازليّ لبثّ مجدوَل — العميل يحسب بساعته من زمن مطلق (آمن للكاش). يبدأ بعد الترطيب
// فلا عدم تطابق SSR. يبثّ كلّ ثانية ويتوقّف عند البدء.
function parts(ms: number) {
  const s = Math.max(0, Math.floor(ms / 1000));
  return {
    d: Math.floor(s / 86400),
    h: Math.floor((s % 86400) / 3600),
    m: Math.floor((s % 3600) / 60),
    s: s % 60,
  };
}

const pad = (n: number) => String(n).padStart(2, '0');

export function BroadcastCountdown({ target, className = '' }: { target: string; className?: string }) {
  const [now, setNow] = useState<number | null>(null);

  useEffect(() => {
    setNow(Date.now());
    const t = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(t);
  }, []);

  if (now === null) {
    return <span className={className} aria-hidden suppressHydrationWarning>—:—:—</span>;
  }

  const diff = new Date(target).getTime() - now;
  if (diff <= 0) return <span className={className}>يبدأ الآن</span>;

  const { d, h, m, s } = parts(diff);
  return (
    <span className={`tabular-nums ${className}`} aria-label="الوقت المتبقّي حتّى البدء">
      {d > 0 ? `${d} يوم · ` : ''}
      {pad(h)}:{pad(m)}:{pad(s)}
    </span>
  );
}
