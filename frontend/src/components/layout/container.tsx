import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

// Single centered layout container — max 1200px, responsive gutters. RTL-safe (logical padding).
export function Container({ className, children }: { className?: string; children: ReactNode }) {
  return <div className={cn('mx-auto w-full max-w-[1200px] px-4 sm:px-6 lg:px-8', className)}>{children}</div>;
}
