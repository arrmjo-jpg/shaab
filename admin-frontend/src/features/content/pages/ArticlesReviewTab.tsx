import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useArticles, useTransitionArticle } from '../hooks';
import { isEditorialUser } from '../lib/workflow';
import type { ArticleData, ArticlesListParams } from '@/types/content.types';
import { REVIEW_STATUS_VARIANT, ReviewActionsCell, fmtDate } from './reviewShared';

const PER_PAGE = 15;

// Articles tab of the review queue. status=submitted; Publish/Reject via the
// existing useTransitionArticle (toast + invalidate). Articles have no media gate
// → always publishable. Actions are editorial-only (isEditorialUser).
export function ArticlesReviewTab() {
  const { t, i18n } = useTranslation('content');
  const { user } = useAuth();
  const { confirm } = useToast();
  const transition = useTransitionArticle();
  const isEditorial = isEditorialUser(user?.roles ?? []);
  const [page, setPage] = useState(1);

  const params: ArticlesListParams = {
    page,
    per_page: PER_PAGE,
    search: '',
    status: 'submitted',
    type: '',
    locale: '',
    category: '',
    placement: '',
    sort: '-created_at',
    trashed: '',
  };
  const q = useArticles(params);

  const act = async (a: ArticleData, target: 'published' | 'rejected') => {
    const ok = await confirm({
      title: t(`reviewQueue.confirm.${target}.title`),
      text: t(`reviewQueue.confirm.${target}.text`),
      confirmText: t('reviewQueue.confirm.yes'),
      cancelText: t('common.cancel', { ns: 'common' }),
    });
    if (!ok) return;
    transition.mutate({ id: a.id, status: target, scheduledAt: null });
  };

  const columns: Column<ArticleData>[] = [
    { key: 'title', header: t('reviewQueue.col.title'), render: (a) => <span className="font-medium">{a.title}</span> },
    { key: 'author', header: t('reviewQueue.col.author'), render: (a) => a.author?.name ?? '—' },
    {
      key: 'date',
      header: t('reviewQueue.col.date'),
      render: (a) => <span className="text-sm text-muted-foreground">{fmtDate(i18n.language, a.created_at)}</span>,
    },
    {
      key: 'status',
      header: t('reviewQueue.col.status'),
      render: (a) => (
        <Badge variant={REVIEW_STATUS_VARIANT[a.status] ?? 'muted'}>{t(`articles.status.${a.status}`)}</Badge>
      ),
    },
    {
      key: 'actions',
      header: t('reviewQueue.col.actions'),
      align: 'end',
      render: (a) => (
        <ReviewActionsCell
          isEditorial={isEditorial}
          pending={transition.isPending}
          ready
          labels={{
            publish: t('reviewQueue.action.publish'),
            reject: t('reviewQueue.action.reject'),
            waitingMedia: t('reviewQueue.waitingMedia'),
            editToFix: t('reviewQueue.editToFix'),
          }}
          onPublish={() => void act(a, 'published')}
          onReject={() => void act(a, 'rejected')}
        />
      ),
    },
  ];

  if (q.isError) {
    return <ErrorState message={t('states.errorTitle', { ns: 'common' })} onRetry={() => void q.refetch()} />;
  }

  return (
    <div className="space-y-4">
      <DataTable
        columns={columns}
        rows={q.data?.data ?? []}
        rowKey={(a) => a.id}
        loading={q.isLoading}
        emptyTitle={t('reviewQueue.empty')}
      />
      {q.data ? <Pagination meta={q.data.pagination} onPage={setPage} /> : null}
    </div>
  );
}
