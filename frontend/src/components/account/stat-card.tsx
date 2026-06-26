import type { ComponentType } from 'react';

import { formatNumber } from '@/lib/format';

export function StatCard({
  label,
  value,
  icon: Icon,
}: {
  label: string;
  value: number;
  icon: ComponentType<{ className?: string }>;
}) {
  return (
    <div className="flex items-center gap-4 rounded-xl border border-border bg-surface p-4">
      <div className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <Icon className="size-5" aria-hidden />
      </div>
      <div className="min-w-0">
        <p className="truncate text-caption text-muted">{label}</p>
        <p className="font-heading text-h3 font-extrabold leading-tight text-fg">{formatNumber(value)}</p>
      </div>
    </div>
  );
}
