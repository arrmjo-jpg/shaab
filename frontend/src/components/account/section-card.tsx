import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export function SectionCard({
  title,
  action,
  children,
  bodyClassName,
}: {
  title?: string;
  action?: ReactNode;
  children: ReactNode;
  bodyClassName?: string;
}) {
  return (
    <section className="overflow-hidden rounded-xl border border-border bg-surface">
      {(title || action) && (
        <div className="flex items-center justify-between gap-2 border-b border-border px-5 py-3.5">
          {title && <h2 className="font-heading text-base font-bold text-fg">{title}</h2>}
          {action}
        </div>
      )}
      <div className={cn('p-5', bodyClassName)}>{children}</div>
    </section>
  );
}
