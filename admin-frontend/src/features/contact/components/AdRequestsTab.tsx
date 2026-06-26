import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';
import { useAdRequests, useMarkAdRead } from '../contact.hooks';
import { AdRequestModal } from './AdRequestModal';
import type { AdListParams, AdRequest, AdRequestStatus } from '@/types/inbox.types';

const selectCls =
  'h-10 rounded-xl border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const PER_PAGE = 20;

const STATUS_TONE: Record<AdRequestStatus, 'default' | 'success' | 'muted' | 'destructive'> = {
  new: 'default',
  contacted: 'default',
  negotiating: 'default',
  completed: 'success',
  rejected: 'destructive',
  closed: 'muted',
};

const STATUSES: AdRequestStatus[] = [
  'new',
  'contacted',
  'negotiating',
  'completed',
  'rejected',
  'closed',
];

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
}

/** تبويب طلبات الإعلان — قائمة + بحث + فلتر حالة + ترقيم + مودال تفاصيل/ملاحظات. */
export function AdRequestsTab() {
  const { t, i18n } = useTranslation('inbox');

  const [params, setParams] = useState<AdListParams>({
    page: 1,
    per_page: PER_PAGE,
    status: '',
    q: '',
    dir: 'desc',
  });
  const [searchInput, setSearchInput] = useState('');
  const debouncedSearch = useDebouncedValue(searchInput, 300);
  useEffect(() => {
    if (debouncedSearch === params.q) return;
    setParams((prev) => ({ ...prev, q: debouncedSearch, page: 1 }));
  }, [debouncedSearch, params.q]);

  const [selectedId, setSelectedId] = useState<number | null>(null);

  const q = useAdRequests(params);
  const markRead = useMarkAdRead();

  const patch = (p: Partial<AdListParams>) =>
    setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const openRow = (row: AdRequest) => {
    setSelectedId(row.id);
    if (!row.is_read) markRead.mutate(row.id);
  };

  const hasFilters = Boolean(searchInput || params.status);

  const columns: Column<AdRequest>[] = [
    {
      key: 'company',
      header: t('ads.col.company'),
      render: (r) => (
        <div className="flex min-w-0 items-center gap-2">
          {!r.is_read ? <span className="h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden /> : null}
          <div className="min-w-0">
            <p className={r.is_read ? 'truncate text-sm' : 'truncate text-sm font-bold'}>{r.company_name}</p>
            <p className="truncate text-xs text-muted-foreground">{r.contact_name}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'adType',
      header: t('ads.col.adType'),
      render: (r) => <span className="text-sm">{r.ad_type}</span>,
    },
    {
      key: 'status',
      header: t('ads.col.status'),
      render: (r) => <Badge variant={STATUS_TONE[r.status]}>{t(`ads.status.${r.status}`)}</Badge>,
    },
    {
      key: 'date',
      header: t('ads.col.date'),
      align: 'end',
      render: (r) => (
        <span className="whitespace-nowrap text-xs text-muted-foreground">
          {fmtDate(r.created_at, i18n.language)}
        </span>
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <Input
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder={t('common.search')}
          className="min-w-[200px] flex-1"
        />
        <select
          className={selectCls}
          value={params.status}
          onChange={(e) => patch({ status: e.target.value as AdListParams['status'] })}
        >
          <option value="">{t('ads.filter.statusAll')}</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`ads.status.${s}`)}
            </option>
          ))}
        </select>
        <select
          className={selectCls}
          value={params.dir}
          onChange={(e) => patch({ dir: e.target.value as AdListParams['dir'] })}
        >
          <option value="desc">{t('common.sort.newest')}</option>
          <option value="asc">{t('common.sort.oldest')}</option>
        </select>
        {hasFilters ? (
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setSearchInput('');
              setParams({ page: 1, per_page: PER_PAGE, status: '', q: '', dir: 'desc' });
            }}
          >
            <X className="h-4 w-4" />
            {t('common.reset')}
          </Button>
        ) : null}
      </div>

      <DataTable
        columns={columns}
        rows={q.data?.data ?? []}
        rowKey={(r) => r.id}
        loading={q.isLoading}
        onRowClick={openRow}
        emptyTitle={t('ads.empty.title')}
        emptyDescription={t('ads.empty.description')}
      />

      {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}

      <AdRequestModal id={selectedId} onClose={() => setSelectedId(null)} />
    </div>
  );
}
