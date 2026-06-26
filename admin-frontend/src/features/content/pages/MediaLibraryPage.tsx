import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState, ErrorState } from '@/components/feedback';
import { SearchInput } from '@/components/data/SearchInput';
import { useMediaLibrary } from '../hooks';
import { MediaAssetDetailPanel } from '../components/media/MediaAssetDetailPanel';
import { MediaCardThumb } from '../components/media/MediaCardThumb';
import type { MediaAssetData, MediaLibraryFilterType } from '@/types/content.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export default function MediaLibraryPage() {
  const { t } = useTranslation('content');

  const [search, setSearch] = useState('');
  const [type, setType] = useState<MediaLibraryFilterType>('');
  const [page, setPage] = useState(1);
  const [activeUuid, setActiveUuid] = useState<string | null>(null);

  const q = useMediaLibrary({ search, type, page, per_page: 24 });
  const pagination = q.data?.pagination;

  const onFilter = (next: () => void) => {
    setPage(1);
    next();
  };

  if (q.isError) {
    return <ErrorState message={t('mediaLibrary.loadError')} onRetry={() => void q.refetch()} />;
  }

  const assets = q.data?.data ?? [];

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('mediaLibrary.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('mediaLibrary.subtitle')}</p>
      </header>

      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <SearchInput
          value={search}
          onDebouncedChange={(v) => onFilter(() => setSearch(v))}
          placeholder={t('mediaLibrary.search')}
        />
        <select
          className={selectCls}
          value={type}
          onChange={(e) => onFilter(() => setType(e.target.value as MediaLibraryFilterType))}
        >
          <option value="">{t('mediaLibrary.filter.typeAll')}</option>
          <option value="image">{t('mediaLibrary.filter.typeImage')}</option>
          <option value="video">{t('mediaLibrary.filter.typeVideo')}</option>
          <option value="external">{t('mediaLibrary.filter.typeExternal')}</option>
        </select>
      </div>

      {q.isLoading ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
          {Array.from({ length: 12 }).map((_, i) => (
            <Skeleton key={i} className="aspect-square w-full" />
          ))}
        </div>
      ) : assets.length === 0 ? (
        <EmptyState title={t('mediaLibrary.empty')} />
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
          {assets.map((asset) => (
            <AssetCard key={asset.id} asset={asset} onOpen={() => setActiveUuid(asset.uuid)} />
          ))}
        </div>
      )}

      {pagination && pagination.total_pages > 1 ? (
        <div className="flex items-center justify-center gap-3">
          <Button
            variant="outline"
            size="sm"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            <ChevronRight className="h-4 w-4" />
            {t('mediaLibrary.pagination.prev')}
          </Button>
          <span className="text-xs text-muted-foreground">
            {t('mediaLibrary.pagination.page', {
              page: pagination.current_page,
              total: pagination.total_pages,
            })}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={page >= pagination.total_pages}
            onClick={() => setPage((p) => p + 1)}
          >
            {t('mediaLibrary.pagination.next')}
            <ChevronLeft className="h-4 w-4" />
          </Button>
        </div>
      ) : null}

      <MediaAssetDetailPanel
        uuid={activeUuid}
        open={activeUuid !== null}
        onClose={() => setActiveUuid(null)}
      />
    </div>
  );
}

function AssetCard({ asset, onOpen }: { asset: MediaAssetData; onOpen: () => void }) {
  const { t } = useTranslation('content');
  const usage = asset.usage_count ?? 0;
  const kindLabel = asset.is_external
    ? t('mediaLibrary.kind.external')
    : asset.is_video
      ? t('mediaLibrary.kind.video')
      : t('mediaLibrary.kind.image');

  return (
    <button
      type="button"
      onClick={onOpen}
      className="group flex flex-col border border-border bg-background text-start transition-colors hover:border-primary"
    >
      <MediaCardThumb asset={asset} />
      <div className="space-y-1.5 p-2">
        <p className="truncate text-xs font-medium" title={asset.original_name}>
          {asset.original_name}
        </p>
        <div className="flex items-center justify-between gap-1">
          <Badge variant="muted" className="px-1.5">
            {kindLabel}
          </Badge>
          <Badge variant={usage > 0 ? 'default' : 'muted'} className="px-1.5">
            {usage > 0 ? t('mediaLibrary.usageBadge', { count: usage }) : t('mediaLibrary.unused')}
          </Badge>
        </div>
      </div>
    </button>
  );
}
