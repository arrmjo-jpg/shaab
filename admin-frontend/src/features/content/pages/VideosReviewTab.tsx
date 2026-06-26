import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { paths } from '@/router/paths';
import { useVideos, useTransitionVideo } from '@/features/video-library/hooks';
import { isEditorialUser } from '../lib/workflow';
import type { VideoData, VideosListParams } from '@/types/videoLibrary.types';
import { REVIEW_STATUS_VARIANT, ReviewActionsCell, fmtDate, isVideoReady } from './reviewShared';

const PER_PAGE = 15;

// Videos tab. status=submitted; Publish/Reject via useTransitionVideo. Publish is
// gated by media readiness (isVideoReady: media_asset_id + external embed OR ready
// processing) — writer videos arrive without media → Publish disabled + "awaiting
// media" badge + Edit link; Reject always available. Editorial-only actions.
// Uses the videoLibrary namespace only for the status label.
export function VideosReviewTab() {
  const { t, i18n } = useTranslation('content');
  const { user } = useAuth();
  const { confirm } = useToast();
  const transition = useTransitionVideo();
  const isEditorial = isEditorialUser(user?.roles ?? []);
  const [page, setPage] = useState(1);

  const params: VideosListParams = {
    page,
    per_page: PER_PAGE,
    search: '',
    status: 'submitted',
    visibility: '',
    source_type: '',
    locale: '',
    sort: '-created_at',
    trashed: '',
  };
  const q = useVideos(params);

  const act = async (v: VideoData, target: 'published' | 'rejected') => {
    const ok = await confirm({
      title: t(`reviewQueue.confirm.${target}.title`),
      text: t(`reviewQueue.confirm.${target}.text`),
      confirmText: t('reviewQueue.confirm.yes'),
      cancelText: t('common.cancel', { ns: 'common' }),
    });
    if (!ok) return;
    transition.mutate({ id: v.id, status: target, publishedAt: null });
  };

  const columns: Column<VideoData>[] = [
    { key: 'title', header: t('reviewQueue.col.title'), render: (v) => <span className="font-medium">{v.title}</span> },
    { key: 'author', header: t('reviewQueue.col.author'), render: (v) => v.author?.name ?? '—' },
    {
      key: 'date',
      header: t('reviewQueue.col.date'),
      render: (v) => <span className="text-sm text-muted-foreground">{fmtDate(i18n.language, v.created_at)}</span>,
    },
    {
      key: 'status',
      header: t('reviewQueue.col.status'),
      render: (v) => (
        <Badge variant={REVIEW_STATUS_VARIANT[v.status] ?? 'muted'}>
          {t(`status.${v.status}`, { ns: 'videoLibrary' })}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: t('reviewQueue.col.actions'),
      align: 'end',
      render: (v) => {
        const ready = isVideoReady(v);
        return (
          <ReviewActionsCell
            isEditorial={isEditorial}
            pending={transition.isPending}
            ready={ready}
            editHref={ready ? undefined : paths.vlVideosEdit.replace(':id', String(v.id))}
            labels={{
              publish: t('reviewQueue.action.publish'),
              reject: t('reviewQueue.action.reject'),
              waitingMedia: t('reviewQueue.waitingMedia'),
              editToFix: t('reviewQueue.editToFix'),
            }}
            onPublish={() => void act(v, 'published')}
            onReject={() => void act(v, 'rejected')}
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
        rowKey={(v) => v.id}
        loading={q.isLoading}
        emptyTitle={t('reviewQueue.empty')}
      />
      {q.data ? <Pagination meta={q.data.pagination} onPage={setPage} /> : null}
    </div>
  );
}
