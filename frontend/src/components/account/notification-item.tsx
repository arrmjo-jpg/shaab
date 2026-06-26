'use client';

import { useTransition } from 'react';

import { markNotificationReadAction } from '@/lib/account-actions';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

export function NotificationItem({
  id,
  message,
  createdAt,
  read,
}: {
  id: string;
  message: string;
  createdAt?: string | null;
  read: boolean;
}) {
  const [pending, startTransition] = useTransition();

  return (
    <div className="flex items-start gap-3 px-5 py-4">
      <span
        className={cn('mt-1.5 size-2 shrink-0 rounded-full', read ? 'bg-border' : 'bg-primary')}
        aria-hidden
      />
      <div className="min-w-0 flex-1">
        <p className={cn('text-sm leading-relaxed', read ? 'text-muted' : 'font-medium text-fg')}>{message}</p>
        <p className="mt-0.5 text-caption text-muted">{formatDate(createdAt)}</p>
      </div>
      {!read && (
        <button
          type="button"
          disabled={pending}
          onClick={() => startTransition(() => markNotificationReadAction(id))}
          className="shrink-0 text-caption font-medium text-primary transition-colors hover:underline disabled:opacity-50"
        >
          {pending ? '…' : 'تعليم كمقروء'}
        </button>
      )}
    </div>
  );
}
