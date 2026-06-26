import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { SearchInput } from '@/components/data/SearchInput';
import { ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import {
  useWriterRequests,
  useApproveWriterRequest,
  useRejectWriterRequest,
} from '../hooks';
import type {
  WriterRequestData,
  WriterRequestsListParams,
} from '@/types/writer.types';

const PER_PAGE = 15;
const selectCls =
  'h-10 rounded-xl border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function WriterRequestsPage() {
  const { t, i18n } = useTranslation('users');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();
  const canReview = hasPermission('writer-requests.review');

  const [params, setParams] = useState<WriterRequestsListParams>({
    page: 1,
    per_page: PER_PAGE,
    search: '',
    status: 'pending',
  });

  const q = useWriterRequests(params);
  const approve = useApproveWriterRequest();
  const reject = useRejectWriterRequest();

  const patch = (p: Partial<WriterRequestsListParams>) =>
    setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const fmtDate = (v: string) =>
    new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(new Date(v));

  const onApprove = async (r: WriterRequestData) => {
    if (
      await confirm({
        title: t('writerRequests.confirm.approveTitle'),
        text: t('writerRequests.confirm.approveText', { name: r.user.name }),
        confirmText: t('users.confirm.yes'),
        cancelText: t('common.cancel'),
      })
    )
      approve.mutate(r.id);
  };

  const onReject = async (r: WriterRequestData) => {
    if (
      await confirm({
        title: t('writerRequests.confirm.rejectTitle'),
        text: t('writerRequests.confirm.rejectText', { name: r.user.name }),
        confirmText: t('users.confirm.yes'),
        cancelText: t('common.cancel'),
      })
    )
      reject.mutate(r.id);
  };

  const columns: Column<WriterRequestData>[] = [
    {
      key: 'user',
      header: t('writerRequests.col.user'),
      render: (r) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{r.user.name}</p>
          <p className="truncate text-xs text-muted-foreground">{r.user.email}</p>
        </div>
      ),
    },
    {
      key: 'note',
      header: t('writerRequests.col.note'),
      render: (r) => (
        <span className="text-sm text-muted-foreground">{r.note || '—'}</span>
      ),
    },
    {
      key: 'status',
      header: t('writerRequests.col.status'),
      align: 'center',
      render: (r) => (
        <Badge
          variant={
            r.status === 'approved'
              ? 'success'
              : r.status === 'rejected'
                ? 'destructive'
                : 'muted'
          }
        >
          {r.status_label}
        </Badge>
      ),
    },
    {
      key: 'created',
      header: t('writerRequests.col.created'),
      render: (r) => (
        <span className="text-sm text-muted-foreground">{fmtDate(r.created_at)}</span>
      ),
    },
    {
      key: 'actions',
      header: t('writerRequests.col.actions'),
      align: 'end',
      render: (r) =>
        canReview && r.status === 'pending' ? (
          <div className="flex items-center justify-end gap-2">
            <Button size="sm" onClick={() => onApprove(r)}>
              <Check className="h-4 w-4" />
              {t('writerRequests.approve')}
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="text-destructive"
              onClick={() => onReject(r)}
            >
              <X className="h-4 w-4" />
              {t('writerRequests.reject')}
            </Button>
          </div>
        ) : (
          <span className="text-xs text-muted-foreground">
            {r.reviewer?.name ?? '—'}
          </span>
        ),
    },
  ];

  if (q.isError) return <ErrorState onRetry={() => void q.refetch()} />;

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('writerRequests.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('writerRequests.subtitle')}</p>
      </header>

      <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-border bg-background p-3">
        <SearchInput
          value={params.search}
          onDebouncedChange={(v) => patch({ search: v })}
          placeholder={t('writerRequests.searchPlaceholder')}
        />
        <select
          className={selectCls}
          value={params.status}
          onChange={(e) =>
            patch({ status: e.target.value as WriterRequestsListParams['status'] })
          }
        >
          <option value="">{t('writerRequests.filter.all')}</option>
          <option value="pending">{t('writerRequests.status.pending')}</option>
          <option value="approved">{t('writerRequests.status.approved')}</option>
          <option value="rejected">{t('writerRequests.status.rejected')}</option>
        </select>
      </div>

      <DataTable
        columns={columns}
        rows={q.data?.data ?? []}
        rowKey={(r) => r.id}
        loading={q.isLoading}
      />

      {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}
    </div>
  );
}
