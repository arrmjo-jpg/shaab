import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';
import { useContactMessages, useMarkContactRead } from '../contact.hooks';
import { ContactMessageModal } from './ContactMessageModal';
import type {
  ContactListParams,
  ContactMessage,
  ContactMessageStatus,
  ContactMessageType,
} from '@/types/inbox.types';

const selectCls =
  'h-10 rounded-xl border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const PER_PAGE = 20;

const STATUS_TONE: Record<ContactMessageStatus, 'default' | 'success' | 'muted'> = {
  new: 'default',
  in_review: 'muted',
  replied: 'success',
  closed: 'muted',
};

const STATUSES: ContactMessageStatus[] = ['new', 'in_review', 'replied', 'closed'];
const TYPES: ContactMessageType[] = ['inquiry', 'complaint', 'suggestion', 'other'];

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
}

/** تبويب رسائل الاتصال — قائمة + بحث + فلاتر (حالة/نوع) + ترقيم + مودال تفاصيل. */
export function ContactMessagesTab() {
  const { t, i18n } = useTranslation('inbox');

  const [params, setParams] = useState<ContactListParams>({
    page: 1,
    per_page: PER_PAGE,
    status: '',
    type: '',
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

  const q = useContactMessages(params);
  const markRead = useMarkContactRead();

  const patch = (p: Partial<ContactListParams>) =>
    setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const openRow = (row: ContactMessage) => {
    setSelectedId(row.id);
    if (!row.is_read) markRead.mutate(row.id);
  };

  const hasFilters = Boolean(searchInput || params.status || params.type);

  const columns: Column<ContactMessage>[] = [
    {
      key: 'sender',
      header: t('contact.col.sender'),
      render: (r) => (
        <div className="flex min-w-0 items-center gap-2">
          {!r.is_read ? <span className="h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden /> : null}
          <div className="min-w-0">
            <p className={r.is_read ? 'truncate text-sm' : 'truncate text-sm font-bold'}>{r.name}</p>
            <p className="truncate text-xs text-muted-foreground">{r.email}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'subject',
      header: t('contact.col.subject'),
      render: (r) => (
        <div className="min-w-0">
          <p className="line-clamp-1 text-sm">{r.subject}</p>
          <Badge variant="muted" className="mt-1">
            {t(`contact.type.${r.type}`)}
          </Badge>
        </div>
      ),
    },
    {
      key: 'status',
      header: t('contact.col.status'),
      render: (r) => <Badge variant={STATUS_TONE[r.status]}>{t(`contact.status.${r.status}`)}</Badge>,
    },
    {
      key: 'date',
      header: t('contact.col.date'),
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
          onChange={(e) => patch({ status: e.target.value as ContactListParams['status'] })}
        >
          <option value="">{t('contact.filter.statusAll')}</option>
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`contact.status.${s}`)}
            </option>
          ))}
        </select>
        <select
          className={selectCls}
          value={params.type}
          onChange={(e) => patch({ type: e.target.value as ContactListParams['type'] })}
        >
          <option value="">{t('contact.filter.typeAll')}</option>
          {TYPES.map((ty) => (
            <option key={ty} value={ty}>
              {t(`contact.type.${ty}`)}
            </option>
          ))}
        </select>
        <select
          className={selectCls}
          value={params.dir}
          onChange={(e) => patch({ dir: e.target.value as ContactListParams['dir'] })}
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
              setParams({ page: 1, per_page: PER_PAGE, status: '', type: '', q: '', dir: 'desc' });
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
        emptyTitle={t('contact.empty.title')}
        emptyDescription={t('contact.empty.description')}
      />

      {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}

      <ContactMessageModal id={selectedId} onClose={() => setSelectedId(null)} />
    </div>
  );
}
