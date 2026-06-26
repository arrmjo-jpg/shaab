import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, Plus, Search, Video as VideoIcon } from 'lucide-react';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Pagination } from '@/components/data/Pagination';
import { cn } from '@/lib/utils';
import { useAttachPlaylistVideos, useVideos } from '../hooks';
import type { VideoData, VideoStatus } from '@/types/videoLibrary.types';

const STATUS_TONE: Record<VideoStatus, 'success' | 'muted'> = {
  published: 'success',
  scheduled: 'muted',
  draft: 'muted',
  archived: 'muted',
  submitted: 'muted',
  in_review: 'muted',
  rejected: 'muted',
};
const PER_PAGE = 8;

interface Props {
  open: boolean;
  onClose: () => void;
  playlistId: number;
  attachedIds: Set<number>;
}

/**
 * منتقي فيديوهات قابل للتوسّع — بحث + ترقيم + بطاقات نقر (لا «كابوس مربّعات تحديد»).
 * الصف كاملاً قابل للنقر بحالة تحديد مرئية؛ المُسنَد مسبقاً يظهر «مُضاف» (معطّل).
 */
export function VideoPickerModal({ open, onClose, playlistId, attachedIds }: Props) {
  const { t } = useTranslation('videoLibrary');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const attach = useAttachPlaylistVideos();

  useEffect(() => {
    if (open) {
      setSearch('');
      setPage(1);
      setSelected(new Set());
    }
  }, [open]);

  const q = useVideos({
    page,
    per_page: PER_PAGE,
    search,
    status: '',
    visibility: '',
    source_type: '',
    locale: '',
    sort: '-created_at',
    trashed: '',
  });

  const toggle = (id: number) =>
    setSelected((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const rows = q.data?.data ?? [];

  const submit = () => {
    if (selected.size === 0) return;
    attach.mutate(
      { id: playlistId, videoIds: [...selected] },
      { onSuccess: () => onClose() },
    );
  };

  const poster = (v: VideoData) => v.share_image ?? v.media?.poster_url ?? null;

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={t('playlists.picker.title')}
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={attach.isPending}>
            {t('common.cancel', { ns: 'common' })}
          </Button>
          <Button onClick={submit} disabled={selected.size === 0 || attach.isPending}>
            <Plus className="h-4 w-4" />
            {t('playlists.picker.add', { count: selected.size })}
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        <div className="relative">
          <Search className="pointer-events-none absolute top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground start-3" />
          <Input
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder={t('playlists.picker.search')}
            className="ps-9"
            autoFocus
          />
        </div>

        {q.isLoading ? (
          <div className="space-y-2">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-16 w-full" />
            ))}
          </div>
        ) : rows.length === 0 ? (
          <p className="py-10 text-center text-sm text-muted-foreground">{t('playlists.picker.empty')}</p>
        ) : (
          <div className="space-y-2">
            {rows.map((v) => {
              const already = attachedIds.has(v.id);
              const isSel = selected.has(v.id);
              return (
                <button
                  key={v.id}
                  type="button"
                  disabled={already}
                  onClick={() => toggle(v.id)}
                  className={cn(
                    'flex w-full items-center gap-3 border p-2 text-start transition-colors',
                    already
                      ? 'cursor-default border-border opacity-60'
                      : isSel
                        ? 'border-primary bg-primary/5'
                        : 'border-border hover:border-primary/60',
                  )}
                >
                  <span className="flex h-12 w-9 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted">
                    {poster(v) ? (
                      <img src={poster(v) as string} alt="" className="h-full w-full object-cover" />
                    ) : (
                      <VideoIcon className="h-4 w-4 text-muted-foreground" />
                    )}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium">{v.title}</span>
                    <span className="mt-0.5 flex items-center gap-1.5">
                      <Badge variant={STATUS_TONE[v.status]}>{t(`status.${v.status}`)}</Badge>
                      <Badge variant="muted">{t(`source.${v.source_type}`)}</Badge>
                    </span>
                  </span>
                  <span className="shrink-0 text-muted-foreground">
                    {already ? (
                      <span className="text-xs">{t('playlists.picker.added')}</span>
                    ) : isSel ? (
                      <Check className="h-5 w-5 text-primary" />
                    ) : (
                      <Plus className="h-5 w-5" />
                    )}
                  </span>
                </button>
              );
            })}
          </div>
        )}

        {q.data && q.data.pagination.total_pages > 1 ? (
          <Pagination meta={q.data.pagination} onPage={setPage} />
        ) : null}
      </div>
    </Modal>
  );
}
