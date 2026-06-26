'use client';

import Link from 'next/link';
import { useState } from 'react';

import { EyeIcon, FileTextIcon, SearchIcon } from '@/components/icons';
import type { ContentItem, ContentType } from '@/lib/account';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

import { EmptyState } from './empty-state';

const STATUSES: { key: string; label: string }[] = [
  { key: 'all', label: 'الكل' },
  { key: 'published', label: 'منشور' },
  { key: 'draft', label: 'مسودّة' },
  { key: 'in_review', label: 'قيد المراجعة' },
  { key: 'rejected', label: 'مرفوض' },
];

const STATUS_LABEL: Record<string, string> = {
  published: 'منشور',
  draft: 'مسودّة',
  submitted: 'مُرسَل',
  in_review: 'قيد المراجعة',
  rejected: 'مرفوض',
};

const STATUS_BADGE: Record<string, string> = {
  published: 'bg-success/10 text-success',
  draft: 'bg-surface-2 text-muted',
  submitted: 'bg-warning/10 text-warning',
  in_review: 'bg-warning/10 text-warning',
  rejected: 'bg-danger/10 text-danger',
};

function StatusBadge({ status }: { status?: string | null }) {
  const key = status ?? '';
  return (
    <span className={cn('inline-block px-2 py-0.5 text-caption font-medium', STATUS_BADGE[key] ?? 'bg-surface-2 text-muted')}>
      {STATUS_LABEL[key] ?? status ?? '—'}
    </span>
  );
}

// Search is client-side over the loaded page (status filter is server-side via the URL).
export function ContentTable({ items, type, status }: { items: ContentItem[]; type: ContentType; status: string }) {
  const [query, setQuery] = useState('');
  const q = query.trim();
  const rows = q ? items.filter((i) => (i.title ?? '').includes(q)) : items;

  return (
    <div className="flex flex-col gap-4">
      {/* Controls */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-1.5">
          {STATUSES.map((s) => (
            <Link
              key={s.key}
              href={`/account/content?tab=${type}&status=${s.key}`}
              className={cn(
                'rounded-full px-3 py-1.5 text-caption font-medium transition-colors',
                status === s.key ? 'bg-primary text-primary-foreground' : 'bg-surface-2 text-muted hover:text-fg',
              )}
            >
              {s.label}
            </Link>
          ))}
        </div>
        <div className="flex items-center gap-2 rounded-lg border border-border bg-surface px-3">
          <SearchIcon className="size-4 shrink-0 text-muted" aria-hidden />
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            type="search"
            placeholder="بحث في العناوين…"
            aria-label="بحث في العناوين"
            className="h-9 w-40 bg-transparent text-sm text-fg outline-none placeholder:text-muted sm:w-52"
          />
        </div>
      </div>

      {rows.length === 0 ? (
        <EmptyState
          icon={FileTextIcon}
          title={q ? 'لا نتائج مطابقة' : 'لا محتوى بعد'}
          description={q ? 'جرّب كلمة بحث أخرى.' : 'لم تُنشئ أيّ محتوى من هذا النوع بعد.'}
        />
      ) : (
        <>
          {/* Desktop table */}
          <div className="hidden overflow-hidden rounded-xl border border-border bg-surface md:block">
            <table className="w-full text-sm">
              <thead className="border-b border-border bg-surface-2 text-caption text-muted">
                <tr>
                  <th className="px-4 py-3 text-start font-medium">العنوان</th>
                  <th className="px-4 py-3 text-start font-medium">الحالة</th>
                  <th className="px-4 py-3 text-start font-medium">التاريخ</th>
                  <th className="px-4 py-3 text-start font-medium">المشاهدات</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {rows.map((item) => (
                  <tr key={item.id} className="transition-colors hover:bg-surface-2/50">
                    <td className="px-4 py-3 font-medium text-fg">
                      <span className="line-clamp-1">{item.title ?? '—'}</span>
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge status={item.status} />
                    </td>
                    <td className="px-4 py-3 text-muted">{formatDate(item.created_at)}</td>
                    <td className="px-4 py-3 text-muted">
                      <span className="inline-flex items-center gap-1">
                        <EyeIcon className="size-4" aria-hidden />
                        {formatNumber(item.metrics?.views ?? 0)}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile stacked cards */}
          <div className="flex flex-col gap-3 md:hidden">
            {rows.map((item) => (
              <div key={item.id} className="flex flex-col gap-2 rounded-xl border border-border bg-surface p-4">
                <p className="line-clamp-2 font-medium text-fg">{item.title ?? '—'}</p>
                <div className="flex items-center justify-between gap-2">
                  <StatusBadge status={item.status} />
                  <div className="flex items-center gap-3 text-caption text-muted">
                    <span className="inline-flex items-center gap-1">
                      <EyeIcon className="size-3.5" aria-hidden />
                      {formatNumber(item.metrics?.views ?? 0)}
                    </span>
                    <span>{formatDate(item.created_at)}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
