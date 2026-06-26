import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, BarChart3, Check, MoreHorizontal, Pencil, Plus, Power, Trash2, Trash, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { usePolls, useDeletePoll, useToggleActivePoll } from '../hooks';
import { type PollData, type PollsListParams, type PollState } from '@/types/polls.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
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

export default function PollsPage() {
  const { t, i18n } = useTranslation('polls');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canCreate = hasPermission('polls.create');
  const canEdit = hasPermission('polls.edit');
  const canPublish = hasPermission('polls.publish');
  const canDelete = hasPermission('polls.delete');
  const canRestore = hasPermission('polls.restore');
  const canForceDelete = hasPermission('polls.force_delete');
  const canSeeTrash = canRestore || canForceDelete;

  const [params, setParams] = useState<PollsListParams>({
    page: 1,
    per_page: PER_PAGE,
    search: '',
    is_active: '',
    sort: '-created_at',
    trashed: '',
  });

  const q = usePolls(params);
  const del = useDeletePoll();
  const toggle = useToggleActivePoll();

  const rows = q.data?.data ?? [];
  const patch = (p: Partial<PollsListParams>) => setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const onToggle = async (p: PollData) => {
    if (
      await confirm({
        title: p.is_active ? t('polls.confirm.deactivateTitle') : t('polls.confirm.activateTitle'),
        text: t(p.is_active ? 'polls.confirm.deactivateText' : 'polls.confirm.activateText', { question: p.question }),
        confirmText: t('polls.confirm.toggle'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      toggle.mutate(p.id);
  };
  const onDelete = async (p: PollData) => {
    if (
      await confirm({
        title: t('polls.confirm.deleteTitle'),
        text: t('polls.confirm.deleteText', { question: p.question }),
        confirmText: t('polls.confirm.delete'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(p.id);
  };

  const columns: Column<PollData>[] = [
    {
      key: 'question',
      header: t('polls.col.question'),
      render: (p) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{p.question}</p>
          {p.allow_multiple ? <p className="truncate text-xs text-muted-foreground">{t('polls.multiple')}</p> : null}
        </div>
      ),
    },
    {
      key: 'state',
      header: t('polls.col.state'),
      render: (p) => <Badge variant={STATE_TONE[p.state]}>{t(`pollState.${p.state}`)}</Badge>,
    },
    {
      key: 'allow_multiple',
      header: t('polls.col.allowMultiple'),
      align: 'center',
      render: (p) =>
        p.allow_multiple ? <Check className="mx-auto h-4 w-4 text-emerald-600 dark:text-emerald-400" /> : <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'options',
      header: t('polls.col.options'),
      align: 'center',
      render: (p) => <span className="tabular-nums text-muted-foreground">{p.options_count ?? 0}</span>,
    },
    {
      key: 'window',
      header: t('polls.col.window'),
      render: (p) => (
        <span className="whitespace-nowrap text-xs text-muted-foreground">
          {!p.starts_at && !p.ends_at
            ? t('polls.noWindow')
            : `${fmtDate(p.starts_at, i18n.language)} – ${fmtDate(p.ends_at, i18n.language)}`}
        </span>
      ),
    },
    {
      key: 'date',
      header: t('polls.col.date'),
      render: (p) => <span className="whitespace-nowrap text-xs text-muted-foreground">{fmtDate(p.created_at, i18n.language)}</span>,
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
            {canEdit ? (
              <DropdownMenuItem onClick={() => navigate(paths.pollEdit.replace(':id', String(p.id)))}>
                <Pencil className="h-4 w-4" />
                {t('polls.action.edit')}
              </DropdownMenuItem>
            ) : null}
            <DropdownMenuItem onClick={() => navigate(paths.pollAnalytics.replace(':id', String(p.id)))}>
              <BarChart3 className="h-4 w-4" />
              {t('polls.action.analytics')}
            </DropdownMenuItem>
            {canPublish ? (
              <DropdownMenuItem onClick={() => void onToggle(p)}>
                <Power className="h-4 w-4" />
                {t(p.is_active ? 'polls.action.deactivate' : 'polls.action.activate')}
              </DropdownMenuItem>
            ) : null}
            {canDelete ? (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => void onDelete(p)} className="text-destructive focus:text-destructive">
                  <Trash2 className="h-4 w-4" />
                  {t('polls.action.delete')}
                </DropdownMenuItem>
              </>
            ) : null}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  const hasFilters = Boolean(params.search || params.is_active);

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('polls.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('polls.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={() => navigate(paths.pollsAnalytics)}>
            <BarChart3 className="h-4 w-4" />
            {t('polls.analytics')}
          </Button>
          {canSeeTrash ? (
            <Button variant="outline" onClick={() => navigate(paths.pollsTrash)}>
              <Trash className="h-4 w-4" />
              {t('polls.trash')}
            </Button>
          ) : null}
          {canCreate ? (
            <Button onClick={() => navigate(paths.pollCreate)}>
              <Plus className="h-4 w-4" />
              {t('polls.new')}
            </Button>
          ) : null}
        </div>
      </header>

      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <Input
          value={params.search}
          onChange={(e) => patch({ search: e.target.value })}
          placeholder={t('polls.filter.search')}
          className="min-w-[200px] flex-1"
        />
        <select
          className={selectCls}
          value={params.is_active}
          onChange={(e) => patch({ is_active: e.target.value as PollsListParams['is_active'] })}
        >
          <option value="">{t('polls.filter.activeAll')}</option>
          <option value="1">{t('polls.filter.activeOnly')}</option>
          <option value="0">{t('polls.filter.inactiveOnly')}</option>
        </select>
        <select className={selectCls} value={params.sort} onChange={(e) => patch({ sort: e.target.value })}>
          <option value="-created_at">{t('polls.filter.sortNewest')}</option>
          <option value="question">{t('polls.filter.sortQuestion')}</option>
        </select>
        {hasFilters ? (
          <Button variant="outline" size="sm" onClick={() => patch({ search: '', is_active: '', sort: '-created_at' })}>
            <X className="h-4 w-4" />
            {t('polls.filter.reset')}
          </Button>
        ) : null}
      </div>

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
            emptyTitle={t('polls.empty.title')}
            emptyDescription={t('polls.empty.description')}
          />
          {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}
        </>
      )}
    </div>
  );
}
