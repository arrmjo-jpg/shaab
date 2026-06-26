import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { ErrorState } from '@/components/feedback';
import { useActivityLog } from '../hooks';
import type { ActivityItem, ActivityListParams } from '@/types/activity.types';

const PER_PAGE = 20;
const LOG_NAMES = [
  'user',
  'role',
  'permission',
  'permission_group',
  'writer_request',
  'media',
  'settings',
  'auth',
];
const EVENTS = ['created', 'updated', 'deleted', 'restored'];

const selectCls =
  'h-10 rounded-xl border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function ActivityLogPage() {
  const { t, i18n } = useTranslation('users');
  const [params, setParams] = useState<ActivityListParams>({
    page: 1,
    per_page: PER_PAGE,
    log_name: '',
    event: '',
    from: '',
    to: '',
  });
  const [open, setOpen] = useState<Set<number>>(new Set());

  const q = useActivityLog(params);

  const patch = (p: Partial<ActivityListParams>) =>
    setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const fmt = (v: string | null) =>
    v
      ? new Intl.DateTimeFormat(i18n.language, {
          dateStyle: 'medium',
          timeStyle: 'short',
        }).format(new Date(v))
      : '—';

  const toggle = (id: number) =>
    setOpen((s) => {
      const n = new Set(s);
      n.has(id) ? n.delete(id) : n.add(id);
      return n;
    });

  const hasDetails = (a: ActivityItem) =>
    Object.keys(a.changes.attributes ?? {}).length > 0 ||
    Object.keys(a.changes.old ?? {}).length > 0 ||
    Object.keys(a.context ?? {}).length > 0;

  const columns: Column<ActivityItem>[] = [
    {
      key: 'when',
      header: t('activityLog.col.when'),
      render: (a) => (
        <span className="whitespace-nowrap text-sm text-muted-foreground">
          {fmt(a.created_at)}
        </span>
      ),
    },
    {
      key: 'desc',
      header: t('activityLog.col.action'),
      render: (a) => (
        <div className="min-w-0">
          <p className="font-medium">{a.description ?? a.event ?? '—'}</p>
          <p className="text-xs text-muted-foreground">
            {a.subject_type ? `${a.subject_type}` : ''}
            {a.subject_id ? ` #${a.subject_id}` : ''}
          </p>
        </div>
      ),
    },
    {
      key: 'log',
      header: t('activityLog.col.module'),
      render: (a) => (
        <Badge variant="muted">
          {a.log_name ? t(`activityLog.log.${a.log_name}`, a.log_name) : '—'}
        </Badge>
      ),
    },
    {
      key: 'causer',
      header: t('activityLog.col.causer'),
      render: (a) => (
        <span className="text-sm text-muted-foreground">
          {a.causer?.name ?? t('activityLog.system')}
        </span>
      ),
    },
    {
      key: 'details',
      header: '',
      align: 'end',
      render: (a) =>
        hasDetails(a) ? (
          <button
            type="button"
            onClick={() => toggle(a.id)}
            className="flex items-center gap-1 text-xs text-primary hover:underline"
          >
            {t('activityLog.details')}
            <ChevronDown
              className={cn('h-3.5 w-3.5 transition-transform', open.has(a.id) && 'rotate-180')}
            />
          </button>
        ) : null,
    },
  ];

  if (q.isError) return <ErrorState onRetry={() => void q.refetch()} />;

  const rows = q.data?.data ?? [];

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('activityLog.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('activityLog.subtitle')}</p>
      </header>

      <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-background p-3">
        <select
          className={selectCls}
          value={params.log_name}
          onChange={(e) => patch({ log_name: e.target.value })}
        >
          <option value="">{t('activityLog.allModules')}</option>
          {LOG_NAMES.map((l) => (
            <option key={l} value={l}>
              {t(`activityLog.log.${l}`, l)}
            </option>
          ))}
        </select>
        <select
          className={selectCls}
          value={params.event}
          onChange={(e) => patch({ event: e.target.value })}
        >
          <option value="">{t('activityLog.allEvents')}</option>
          {EVENTS.map((ev) => (
            <option key={ev} value={ev}>
              {t(`activityLog.event.${ev}`)}
            </option>
          ))}
        </select>
        <label className="flex items-center gap-2 text-xs text-muted-foreground">
          {t('activityLog.from')}
          <input
            type="date"
            className={selectCls}
            value={params.from}
            onChange={(e) => patch({ from: e.target.value })}
          />
        </label>
        <label className="flex items-center gap-2 text-xs text-muted-foreground">
          {t('activityLog.to')}
          <input
            type="date"
            className={selectCls}
            value={params.to}
            onChange={(e) => patch({ to: e.target.value })}
          />
        </label>
      </div>

      <div className="space-y-2">
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(a) => a.id}
          loading={q.isLoading}
        />

        {rows
          .filter((a) => open.has(a.id))
          .map((a) => (
            <div
              key={`d-${a.id}`}
              className="rounded-2xl border border-border bg-muted/30 p-4 text-sm"
            >
              <p className="mb-2 font-semibold">
                #{a.id} — {a.description ?? a.event}
              </p>
              {Object.keys(a.changes.attributes ?? {}).length > 0 ? (
                <div className="grid gap-1.5 sm:grid-cols-2">
                  {Object.entries(a.changes.attributes ?? {}).map(([k, v]) => (
                    <div key={k} className="rounded-lg border border-border px-3 py-2">
                      <span className="text-xs text-muted-foreground">{k}</span>
                      <div className="flex flex-wrap items-center gap-2" dir="ltr">
                        {a.changes.old && k in a.changes.old ? (
                          <>
                            <s className="text-destructive/80">
                              {String(a.changes.old[k] ?? '∅')}
                            </s>
                            <span>→</span>
                          </>
                        ) : null}
                        <span className="font-medium">{String(v ?? '∅')}</span>
                      </div>
                    </div>
                  ))}
                </div>
              ) : null}
              {Object.keys(a.context ?? {}).length > 0 ? (
                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                  {Object.entries(a.context).map(([k, v]) => (
                    <span key={k} dir="ltr">
                      {k}: {Array.isArray(v) ? v.join(', ') : String(v)}
                    </span>
                  ))}
                </div>
              ) : null}
            </div>
          ))}
      </div>

      {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}
    </div>
  );
}
