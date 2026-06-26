import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/feedback';
import { cn } from '@/lib/utils';

export interface Column<T> {
  key: string;
  /** نص أو عقدة (يسمح بمربّع تحديد-الكل في الترويسة). متوافق رجعياً مع النصوص. */
  header: ReactNode;
  render: (row: T) => ReactNode;
  className?: string;
  align?: 'start' | 'center' | 'end';
}

interface DataTableProps<T> {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  loading?: boolean;
  emptyTitle?: string;
  emptyDescription?: string;
  onRowClick?: (row: T) => void;
}

const ALIGN: Record<NonNullable<Column<unknown>['align']>, string> = {
  start: 'text-start',
  center: 'text-center',
  end: 'text-end',
};

export function DataTable<T>({
  columns,
  rows,
  rowKey,
  loading,
  emptyTitle,
  emptyDescription,
  onRowClick,
}: DataTableProps<T>) {
  const { t } = useTranslation();

  return (
    <div className="overflow-hidden rounded-2xl border border-border bg-background">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] border-collapse text-sm">
          <thead>
            <tr className="border-b border-border bg-muted/40">
              {columns.map((c) => (
                <th
                  key={c.key}
                  className={cn(
                    'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground',
                    ALIGN[c.align ?? 'start'],
                    c.className,
                  )}
                >
                  {c.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {loading
              ? Array.from({ length: 6 }).map((_, i) => (
                  <tr key={`sk-${i}`} className="border-b border-border last:border-0">
                    {columns.map((c) => (
                      <td key={c.key} className="px-4 py-3.5">
                        <Skeleton className="h-5 w-full max-w-[140px]" />
                      </td>
                    ))}
                  </tr>
                ))
              : rows.map((row) => (
                  <tr
                    key={rowKey(row)}
                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                    className={cn(
                      'border-b border-border transition-colors last:border-0 hover:bg-accent/40',
                      onRowClick && 'cursor-pointer',
                    )}
                  >
                    {columns.map((c) => (
                      <td
                        key={c.key}
                        className={cn('px-4 py-3.5 align-middle', ALIGN[c.align ?? 'start'], c.className)}
                      >
                        {c.render(row)}
                      </td>
                    ))}
                  </tr>
                ))}
          </tbody>
        </table>
      </div>

      {!loading && rows.length === 0 ? (
        <EmptyState title={emptyTitle ?? t('states.emptyTitle')} description={emptyDescription} />
      ) : null}
    </div>
  );
}
