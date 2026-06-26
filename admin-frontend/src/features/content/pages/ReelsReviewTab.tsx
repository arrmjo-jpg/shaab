import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { paths } from '@/router/paths';
import { useReels, useTransitionReel } from '../reels.hooks';
import { isEditorialUser } from '../lib/workflow';
import type { ReelData, ReelsListParams } from '@/types/content.types';
import { REVIEW_STATUS_VARIANT, ReviewActionsCell, fmtDate, isReelReady } from './reviewShared';

const PER_PAGE = 15;

// Reels tab. status=submitted; Publish/Reject via useTransitionReel. Publish is
// gated by media readiness (isReelReady) — writer reels arrive without media, so
// Publish stays disabled with an "awaiting media" badge + Edit link; Reject is
// always available. Editorial-only actions.
export function ReelsReviewTab() {
  const { t, i18n } = useTranslation('content');
  const { user } = useAuth();
  const { confirm } = useToast();
  const transition = useTransitionReel();
  const isEditorial = isEditorialUser(user?.roles ?? []);
  const [page, setPage] = useState(1);

  const params: ReelsListParams = {
    page,
    per_page: PER_PAGE,
    search: '',
    status: 'submitted',
    locale: '',
    sort: '-created_at',
    trashed: '',
  };
  const q = useReels(params);

  const act = async (r: ReelData, target: 'published' | 'rejected') => {
    const ok = await confirm({
      title: t(`reviewQueue.confirm.${target}.title`),
      text: t(`reviewQueue.confirm.${target}.text`),
      confirmText: t('reviewQueue.confirm.yes'),
      cancelText: t('common.cancel', { ns: 'common' }),
    });
    if (!ok) return;
    transition.mutate({ id: r.id, status: target, publishedAt: null });
  };

  const columns: Column<ReelData>[] = [
    { key: 'title', header: t('reviewQueue.col.title'), render: (r) => <span className="font-medium">{r.title}</span> },
    { key: 'author', header: t('reviewQueue.col.author'), render: (r) => r.author?.name ?? '—' },
    {
      key: 'date',
      header: t('reviewQueue.col.date'),
      render: (r) => <span className="text-sm text-muted-foreground">{fmtDate(i18n.language, r.created_at)}</span>,
    },
    {
      key: 'status',
      header: t('reviewQueue.col.status'),
      render: (r) => (
        <Badge variant={REVIEW_STATUS_VARIANT[r.status] ?? 'muted'}>{t(`reels.status.${r.status}`)}</Badge>
      ),
    },
    {
      key: 'actions',
      header: t('reviewQueue.col.actions'),
      align: 'end',
      render: (r) => {
        const ready = isReelReady(r);
        return (
          <ReviewActionsCell
            isEditorial={isEditorial}
            pending={transition.isPending}
            ready={ready}
            editHref={ready ? undefined : paths.reelsEdit.replace(':id', String(r.id))}
            labels={{
              publish: t('reviewQueue.action.publish'),
              reject: t('reviewQueue.action.reject'),
              waitingMedia: t('reviewQueue.waitingMedia'),
              editToFix: t('reviewQueue.editToFix'),
            }}
            onPublish={() => void act(r, 'published')}
            onReject={() => void act(r, 'rejected')}
          />
        );
      },
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
        rowKey={(r) => r.id}
        loading={q.isLoading}
        emptyTitle={t('reviewQueue.empty')}
      />
      {q.data ? <Pagination meta={q.data.pagination} onPage={setPage} /> : null}
    </div>
  );
}
