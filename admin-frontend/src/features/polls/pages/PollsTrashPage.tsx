import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, ArchiveRestore, ArrowRight, MoreHorizontal, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { usePolls, useRestorePoll, useForceDeletePoll } from '../hooks';
import { type PollData, type PollsListParams, type PollState } from '@/types/polls.types';

const PER_PAGE = 15;

const STATE_TONE: Record<PollState, 'default' | 'success' | 'muted'> = {
  inactive: 'muted',
  scheduled: 'default',
  open: 'success',
  closed: 'muted',
};

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function PollsTrashPage() {
  const { t, i18n } = useTranslation('polls');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canRestore = hasPermission('polls.restore');
  const canForceDelete = hasPermission('polls.force_delete');

  const [params, setParams] = useState<PollsListParams>({
    page: 1,
    per_page: PER_PAGE,
    search: '',
    is_active: '',
    sort: '-created_at',
    trashed: 'only',
  });

  const q = usePolls(params);
  const restore = useRestorePoll();
  const forceDel = useForceDeletePoll();

  const rows = q.data?.data ?? [];
  const patch = (p: Partial<PollsListParams>) => setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const onForceDelete = async (p: PollData) => {
    if (
      await confirm({
        title: t('polls.confirm.forceTitle'),
        text: t('polls.confirm.forceText', { question: p.question }),
        confirmText: t('polls.confirm.forceYes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      forceDel.mutate(p.id);
  };

  const columns: Column<PollData>[] = [
    {
      key: 'question',
      header: t('polls.col.question'),
      render: (p) => <p className="truncate font-medium">{p.question}</p>,
    },
    {
      key: 'state',
      header: t('polls.col.state'),
      render: (p) => <Badge variant={STATE_TONE[p.state]}>{t(`pollState.${p.state}`)}</Badge>,
    },
    {
      key: 'options',
      header: t('polls.col.options'),
      align: 'center',
      render: (p) => <span className="tabular-nums text-muted-foreground">{p.options_count ?? 0}</span>,
    },
    {
      key: 'deleted',
      header: t('polls.col.deletedAt'),
      render: (p) => <span className="whitespace-nowrap text-xs text-muted-foreground">{fmtDate(p.deleted_at, i18n.language)}</span>,
    },
    {
      key: 'actions',
      header: '',
      align: 'end',
      render: (p) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {canRestore ? (
              <DropdownMenuItem onClick={() => restore.mutate(p.id)}>
                <ArchiveRestore className="h-4 w-4" />
                {t('polls.action.restore')}
              </DropdownMenuItem>
            ) : null}
            {canForceDelete ? (
              <DropdownMenuItem onClick={() => void onForceDelete(p)} className="text-destructive focus:text-destructive">
                <Trash2 className="h-4 w-4" />
                {t('polls.action.forceDelete')}
              </DropdownMenuItem>
            ) : null}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('polls.trashTitle')}</h1>
          <p className="text-sm text-muted-foreground">{t('polls.trashSubtitle')}</p>
        </div>
        <Button variant="outline" size="sm" onClick={() => navigate(paths.polls)}>
          <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          {t('polls.form.back')}
        </Button>
      </header>

      {q.isError ? (
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-4 w-4" />
            {t('polls.error')}
          </span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('polls.retry')}
          </Button>
        </div>
      ) : (
        <>
          <DataTable
            columns={columns}
            rows={rows}
            rowKey={(p) => p.id}
            loading={q.isLoading}
            emptyTitle={t('polls.empty.trashTitle')}
            emptyDescription={t('polls.empty.trashDescription')}
          />
          {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}
        </>
      )}
    </div>
  );
}
