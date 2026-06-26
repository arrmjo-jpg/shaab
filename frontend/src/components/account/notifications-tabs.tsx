import Link from 'next/link';

import type { NotificationFilter } from '@/lib/account';
import { cn } from '@/lib/utils';

const TABS: { key: NotificationFilter; label: string }[] = [
  { key: 'all', label: 'الكل' },
  { key: 'unread', label: 'غير المقروء' },
  { key: 'read', label: 'المقروء' },
];

export function NotificationsTabs({ active }: { active: NotificationFilter }) {
  return (
    <div className="flex gap-1 border-b border-border">
      {TABS.map((t) => (
        <Link
          key={t.key}
          href={`/account/notifications?filter=${t.key}`}
          aria-current={active === t.key ? 'page' : undefined}
          className={cn(
            '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
            active === t.key ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-fg',
          )}
        >
          {t.label}
        </Link>
      ))}
    </div>
  );
}
