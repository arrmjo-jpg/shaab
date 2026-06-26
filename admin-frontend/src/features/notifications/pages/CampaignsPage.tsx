import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, Plus, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useCampaigns } from '../hooks';
import { ALL_PRIORITIES, ALL_SOURCES, ALL_STATUSES, STATUS_TONE } from '../constants';
import type { CampaignData, CampaignsListParams } from '@/types/notifications.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const PER_PAGE = 15;

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function CampaignsPage() {
  const { t, i18n } = useTranslation('notifications');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const canSend = hasPermission('notifications.send');

  const [params, setParams] = useState<CampaignsListParams>({
    page: 1,
    per_page: PER_PAGE,
    status: '',
    source: '',
    priority: '',
    sort: '-created_at',
  });

  const q = useCampaigns(params);
  const rows = q.data?.data ?? [];
  const patch = (p: Partial<CampaignsListParams>) =>
    setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const columns: Column<CampaignData>[] = [
    {
      key: 'title',
      header: t('campaigns.col.title'),
      render: (c) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{c.title ?? c.event_label}</p>
          <p className="truncate text-xs text-muted-foreground">{c.event_label}</p>
        </div>
      ),
    },
    {
      key: 'status',
      header: t('campaigns.col.status'),
      render: (c) => <Badge variant={STATUS_TONE[c.status]}>{c.status_label}</Badge>,
    },
    {
      key: 'priority',
      header: t('campaigns.col.priority'),
      render: (c) => (
        <span className="text-sm text-muted-foreground">
          {c.priority ? t(`priority.${c.priority}`) : '—'}
        </span>
      ),
    },
    {
      key: 'channels',
      header: t('campaigns.col.channels'),
      align: 'center',
      render: (c) => <span className="tabular-nums text-muted-foreground">{c.stats.channels}</span>,
    },
    {
      key: 'sent',
      header: t('campaigns.col.sent'),
      align: 'center',
      render: (c) => <span className="tabular-nums">{c.stats.sent}</span>,
    },
    {
      key: 'created',
      header: t('campaigns.col.created'),
      render: (c) => (
        <span className="whitespace-nowrap text-xs text-muted-foreground">
          {fmtDate(c.created_at, i18n.language)}
        </span>
      ),
    },
  ];

  const hasFilters = Boolean(params.status || params.source || params.priority);

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('campaigns.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('campaigns.subtitle')}</p>
        </div>
        {canSend ? (
          <Button onClick={() => navigate(paths.notifCampaignCompose)}>
            <Plus className="h-4 w-4" />
            {t('campaigns.new')}
          </Button>
        ) : null}
      </header>

      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <select className={selectCls} value={params.status} onChange={(e) => patch({ status: e.target.value })}>
          <option value="">{t('campaigns.filter.status')}</option>
          {ALL_STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`status.${s}`)}
            </option>
          ))}
        </select>
        <select className={selectCls} value={params.source} onChange={(e) => patch({ source: e.target.value })}>
          <option value="">{t('campaigns.filter.source')}</option>
          {ALL_SOURCES.map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
        <select className={selectCls} value={params.priority} onChange={(e) => patch({ priority: e.target.value })}>
          <option value="">{t('campaigns.filter.priority')}</option>
          {ALL_PRIORITIES.map((p) => (
            <option key={p} value={p}>
              {t(`priority.${p}`)}
            </option>
          ))}
        </select>
        {hasFilters ? (
          <Button variant="outline" size="sm" onClick={() => patch({ status: '', source: '', priority: '' })}>
            <X className="h-4 w-4" />
            {t('campaigns.filter.reset')}
          </Button>
        ) : null}
      </div>

      {q.isError ? (
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-4 w-4" />
            {t('campaigns.error')}
          </span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : (
        <>
          <DataTable
            columns={columns}
            rows={rows}
            rowKey={(c) => c.uuid}
            loading={q.isLoading}
            emptyTitle={t('campaigns.empty.title')}
            emptyDescription={t('campaigns.empty.description')}
            onRowClick={(c) => navigate(paths.notifCampaignDetail.replace(':uuid', c.uuid))}
          />
          {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}
        </>
      )}
    </div>
  );
}
