import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Ban, Check, MessageSquare, MoreHorizontal, Trash2, X } from 'lucide-react';
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
import { useAuth } from '@/hooks/useAuth';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';
import { useToast } from '@/hooks/useToast';
import { useComments, useDeleteComment, useModerateComment } from '../comments.hooks';
import type { AdminComment, CommentStatus, CommentsListParams, ModerationStatus } from '@/types/comments.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const PER_PAGE = 20;

const STATUS_TONE: Record<CommentStatus, 'success' | 'muted'> = {
  approved: 'success',
  pending: 'muted',
  rejected: 'muted',
  spam: 'muted',
};

const MODERATIONS: ModerationStatus[] = ['approved', 'rejected', 'spam'];

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
}

/**
 * إشراف التعليقات (لوحة الإدارة) — قائمة + فلتر حالة + اعتماد/رفض/سبام/حذف.
 * يستهلك نقاط Slice 1/2 القائمة؛ الإجراءات مُقيَّدة بالصلاحيات (approve/delete).
 */
export default function CommentsModerationPage() {
  const { t, i18n } = useTranslation('content');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canModerate = hasPermission('comments.approve');
  const canDelete = hasPermission('comments.delete');

  const [params, setParams] = useState<CommentsListParams>({ page: 1, per_page: PER_PAGE, status: '', q: '' });
  const [searchInput, setSearchInput] = useState('');
  const debouncedSearch = useDebouncedValue(searchInput, 300);
  useEffect(() => {
    if (debouncedSearch === params.q) return;
    setParams((prev) => ({ ...prev, q: debouncedSearch, page: 1 }));
  }, [debouncedSearch, params.q]);

  const q = useComments(params);
  const moderate = useModerateComment();
  const del = useDeleteComment();

  const patch = (p: Partial<CommentsListParams>) =>
    setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const onModerate = async (r: AdminComment, status: ModerationStatus) => {
    if (
      await confirm({
        title: t(`comments.confirm.${status}Title`),
        text: t('comments.confirm.text'),
        confirmText: t(`comments.action.${status}`),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      moderate.mutate({ id: r.id, status });
  };

  const onDelete = async (r: AdminComment) => {
    if (
      await confirm({
        title: t('comments.confirm.deleteTitle'),
        text: t('comments.confirm.text'),
        confirmText: t('comments.action.delete'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(r.id);
  };

  const columns: Column<AdminComment>[] = [
    {
      key: 'comment',
      header: t('comments.col.comment'),
      render: (r) => (
        <div className="min-w-0">
          <p className="line-clamp-2 text-sm">{r.body}</p>
          <p className="truncate text-xs text-muted-foreground">
            {r.author.name ?? '—'}
            {r.author.is_guest ? <span className="ms-1">· {t('comments.guest')}</span> : null}
            {r.parent_id !== null ? <span className="ms-1">· {t('comments.reply')}</span> : null}
          </p>
        </div>
      ),
    },
    {
      key: 'status',
      header: t('comments.col.status'),
      render: (r) => <Badge variant={STATUS_TONE[r.status]}>{t(`comments.status.${r.status}`)}</Badge>,
    },
    {
      key: 'date',
      header: t('comments.col.date'),
      render: (r) => (
        <span className="whitespace-nowrap text-xs text-muted-foreground">{fmtDate(r.created_at, i18n.language)}</span>
      ),
    },
    {
      key: 'actions',
      header: '',
      align: 'end',
      render: (r) =>
        canModerate || canDelete ? (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {canModerate
                ? MODERATIONS.filter((s) => s !== r.status).map((s) => (
                    <DropdownMenuItem key={s} onClick={() => void onModerate(r, s)}>
                      {s === 'approved' ? <Check className="h-4 w-4" /> : s === 'rejected' ? <X className="h-4 w-4" /> : <Ban className="h-4 w-4" />}
                      {t(`comments.action.${s}`)}
                    </DropdownMenuItem>
                  ))
                : null}
              {canDelete ? (
                <>
                  {canModerate ? <DropdownMenuSeparator /> : null}
                  <DropdownMenuItem
                    onClick={() => void onDelete(r)}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4" />
                    {t('comments.action.delete')}
                  </DropdownMenuItem>
                </>
              ) : null}
            </DropdownMenuContent>
          </DropdownMenu>
        ) : null,
    },
  ];

  return (
    <div className="space-y-6">
      <header className="flex items-center gap-2">
        <MessageSquare className="h-6 w-6 text-muted-foreground" />
        <div>
          <h1 className="text-2xl font-bold">{t('comments.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('comments.subtitle')}</p>
        </div>
      </header>

      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <Input
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder={t('comments.filter.search')}
          className="min-w-[200px] flex-1"
        />
        <select
          className={selectCls}
          value={params.status}
          onChange={(e) => patch({ status: e.target.value as CommentsListParams['status'] })}
        >
          <option value="">{t('comments.filter.statusAll')}</option>
          <option value="pending">{t('comments.status.pending')}</option>
          <option value="approved">{t('comments.status.approved')}</option>
          <option value="rejected">{t('comments.status.rejected')}</option>
          <option value="spam">{t('comments.status.spam')}</option>
        </select>
        {searchInput || params.status ? (
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setSearchInput('');
              setParams({ page: 1, per_page: PER_PAGE, status: '', q: '' });
            }}
          >
            <X className="h-4 w-4" />
            {t('comments.filter.reset')}
          </Button>
        ) : null}
      </div>

      <DataTable
        columns={columns}
        rows={q.data?.data ?? []}
        rowKey={(r) => r.id}
        loading={q.isLoading}
        emptyTitle={t('comments.empty.title')}
        emptyDescription={t('comments.empty.description')}
      />

      {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}
    </div>
  );
}
