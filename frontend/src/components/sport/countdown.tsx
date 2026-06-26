'use client';

import { useEffect, useState } from 'react';

// عدّاد تنازليّ حيّ حتى موعد انطلاق المباراة (interaction كـ365). يعتمد startTime الحقيقيّ — لا تلفيق.
// SSR يعرض فراغاً ثمّ يُرطَّب على العميل (تفادي عدم تطابق الترطيب).
export function Countdown({ to }: { to: string }) {
  const [label, setLabel] = useState<string>('');

  useEffect(() => {
    const target = new Date(to).getTime();
    if (Number.isNaN(target)) return;
    const pad = (n: number) => String(n).padStart(2, '0');
    const tick = () => {
      const diff = target - Date.now();
      if (diff <= 0) {
        setLabel('00:00:00');
        return;
      }
      const h = Math.floor(diff / 3_600_000);
      const m = Math.floor((diff % 3_600_000) / 60_000);
      const s = Math.floor((diff % 60_000) / 1000);
      setLabel(`${pad(h)}:${pad(m)}:${pad(s)}`);
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [to]);

  return <span className="tabular-nums">{label || '—'}</span>;
}
