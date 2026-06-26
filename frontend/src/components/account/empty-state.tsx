import type { ComponentType, ReactNode } from 'react';

import { cn } from '@/lib/utils';

// Professional empty state — large icon "illustration" + message + optional CTA. Used for both
// genuinely-empty data and sections whose backend API does not exist yet.
export function EmptyState({
  icon: Icon,
  title,
  description,
  action,
  className,
}: {
  icon: ComponentType<{ className?: string }>;
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-border bg-surface-2 px-6 py-16 text-center',
        className,
      )}
    >
      <div className="flex size-16 items-center justify-center bg-surface text-muted">
        <Icon className="size-8" aria-hidden />
      </div>
      <div className="space-y-1.5">
        <h3 className="font-heading text-h3 font-bold text-fg">{title}</h3>
        {description && <p className="mx-auto max-w-sm text-sm leading-relaxed text-muted">{description}</p>}
      </div>
      {action}
    </div>
  );
}
