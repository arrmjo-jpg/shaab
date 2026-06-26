'use client';

import Link from 'next/link';
import { usePathname, useSearchParams } from 'next/navigation';

import { cn } from '@/lib/utils';

import { navForUser } from './account-nav';

// Role-filtered nav with active state (pathname + ?tab for content sub-items). Wrap in <Suspense>
// at the call site (uses useSearchParams).
export function DashboardNavLinks({
  isWriter,
  onNavigate,
}: {
  isWriter: boolean;
  onNavigate?: () => void;
}) {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const currentTab = searchParams.get('tab') ?? 'articles';
  const items = navForUser(isWriter);

  return (
    <nav className="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-3" aria-label="لوحة التحكم">
      {items.map((item) => {
        const Icon = item.icon;
        const active = pathname === item.match && (!item.tab || item.tab === currentTab);
        return (
          <Link
            key={item.href}
            href={item.href}
            onClick={onNavigate}
            aria-current={active ? 'page' : undefined}
            className={cn(
              'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
              active ? 'bg-primary/10 text-primary' : 'text-fg hover:bg-surface-2',
            )}
          >
            <Icon className="size-5 shrink-0" aria-hidden />
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}
