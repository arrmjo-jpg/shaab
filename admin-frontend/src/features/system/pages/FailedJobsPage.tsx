import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RefreshCw, Trash2, RotateCcw, Search, AlertTriangle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data/DataTable';
import { ErrorState } from '@/components/feedback';
import { useToast } from '@/hooks/useToast';
import { useAuth } from '@/hooks/useAuth';
import { useFailedJobs, useRetryFailedJobs, useDeleteFailedJobs } from '../hooks';
import type { FailedJob } from '@/types/system.types';

export default function FailedJobsPage() {
  const { t, i18n } = useTranslation('system');
  const { hasPermission } = useAuth();
  const canManage = hasPermission('failed_jobs.manage');
  const { confirm } = useToast();

  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<Set<string>>(new Set());

  const query = useFailedJobs({ q, page, per_page: 20 });
  const retry = useRetryFailedJobs();
  const remove = useDeleteFailedJobs();

  const rows = query.data?.data ?? [];
  const meta = query.data?.meta;

  const fmt = (v: string | null) =>
    v
      ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'short', timeStyle: 'short' }).format(
          new Date(v),
        )
      : '—';

  const clearSelection = () => setSelected(new Set());

  const toggle = (id: string) =>
    setSelected((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const allOnPageSelected = rows.length > 0 && rows.every((r) => selected.has(r.id));
  const toggleAllOnPage = () =>
    setSelected((prev) => {
      const next = new Set(prev);
      if (allOnPageSelected) {
        rows.forEach((r) => next.delete(r.id));
      } else {
        rows.forEach((r) => next.add(r.id));
      }
      return next;
    });

  const submitSearch = () => {
    setPage(1);
    setQ(search.trim());
  };

  const runRetry = (ids: string[]) =>
    retry.mutate({ ids }, { onSuccess: clearSelection });

  const runDelete = (ids: string[]) =>
    remove.mutate({ ids }, { onSuccess: clearSelection });

  const retryAll = async () => {
    if (
      await confirm({
        title: t('failed.confirm.retryAllTitle'),
        text: t('failed.confirm.retryAllText'),
        confirmText: t('failed.action.retryAll'),
        cancelText: t('failed.confirm.cancel'),
      })
    ) {
      retry.mutate({ all: true }, { onSuccess: clearSelection });
    }
  };

  const deleteAll = async () => {
    if (
      await confirm({
        title: t('failed.confirm.deleteAllTitle'),
        text: t('failed.confirm.deleteAllText'),
        confirmText: t('failed.action.deleteAll'),
        cancelText: t('failed.confirm.cancel'),
      })
    ) {
      remove.mutate({ all: true }, { onSuccess: clearSelection });
    }
  };

  const busy = retry.isPending || remove.isPending;

  const columns: Column<FailedJob>[] = [
    {
      key: 'select',
      header: '',
      render: (r) =>
        canManage ? (
          <input
            type="checkbox"
            className="h-4 w-4"
            checked={selected.has(r.id)}
            onChange={() => toggle(r.id)}
            aria-label={t('failed.select')}
          />
        ) : null,
    },
    {
      key: 'job',
      header: t('failed.col.job'),
      render: (r) => (
        <div className="min-w-0">
          <p className="font-medium" dir="ltr">
            {r.name}
          </p>
          <div className="mt-1 flex items-center gap-2">
            <Badge variant="muted">{r.queue ?? '—'}</Badge>
            {r.max_tries != null ? (
              <span className="text-xs text-muted-foreground">
                {t('failed.maxTries')}: {r.max_tries}
              </span>
            ) : null}
          </div>
        </div>
      ),
    },
    {
      key: 'exception',
      header: t('failed.col.exception'),
      render: (r) => (
        <p
          className="flex max-w-md items-start gap-1 text-xs text-destructive"
          title={r.exception}
          dir="ltr"
        >
          <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0" />
          <span className="line-clamp-2">{r.exception}</span>
        </p>
      ),
    },
    {
      key: 'failed_at',
      header: t('failed.col.failedAt'),
      render: (r) => (
        <span className="whitespace-nowrap text-sm text-muted-foreground" dir="ltr">
          {fmt(r.failed_at)}
        </span>
      ),
    },
    {
      key: 'actions',
      header: t('failed.col.actions'),
      align: 'end',
      render: (r) =>
        canManage ? (
          <div className="flex items-center justify-end gap-2">
            <Button variant="outline" size="sm" disabled={busy} onClick={() => runRetry([r.id])}>
              <RotateCcw className="h-4 w-4" />
              {t('failed.action.retry')}
            </Button>
            <Button variant="ghost" size="sm" disabled={busy} onClick={() => runDelete([r.id])}>
              <Trash2 className="h-4 w-4" />
            </Button>
          </div>
        ) : null,
    },
  ];

  if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('failed.title')}</h1>
        <p className="mt-1 text-sm text-muted-foreground">{t('failed.subtitle')}</p>
      </header>

      {/* شريط الأدوات: بحث + إجراءات جماعية */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-2">
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && submitSearch()}
            placeholder={t('failed.searchPlaceholder')}
            className="w-64"
          />
          <Button variant="outline" size="sm" onClick={submitSearch}>
            <Search className="h-4 w-4" />
          </Button>
        </div>

        {canManage ? (
          <div className="flex flex-wrap items-center gap-2">
            <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <input
                type="checkbox"
                className="h-4 w-4"
                checked={allOnPageSelected}
                onChange={toggleAllOnPage}
              />
              {t('failed.selectAll')}
            </label>
            <Button
              variant="outline"
              size="sm"
              disabled={selected.size === 0 || busy}
              onClick={() => runRetry([...selected])}
            >
              <RotateCcw className="h-4 w-4" />
              {t('failed.action.retrySelected', { count: selected.size })}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={selected.size === 0 || busy}
              onClick={() => runDelete([...selected])}
            >
              <Trash2 className="h-4 w-4" />
              {t('failed.action.deleteSelected', { count: selected.size })}
            </Button>
            <Button variant="ghost" size="sm" disabled={busy} onClick={() => void retryAll()}>
              <RefreshCw className="h-4 w-4" />
              {t('failed.action.retryAll')}
            </Button>
            <Button variant="ghost" size="sm" disabled={busy} onClick={() => void deleteAll()}>
              <Trash2 className="h-4 w-4" />
              {t('failed.action.deleteAll')}
            </Button>
          </div>
        ) : null}
      </div>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(r) => r.id}
        loading={query.isLoading}
        emptyTitle={t('failed.empty')}
      />

      {/* الترقيم */}
      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">
            {t('failed.pageOf', { page: meta.page, last: meta.last_page, total: meta.total })}
          </span>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={meta.page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              {t('failed.prev')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={meta.page >= meta.last_page}
              onClick={() => setPage((p) => p + 1)}
            >
              {t('failed.next')}
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
