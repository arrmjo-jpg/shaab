import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, Plus } from 'lucide-react';
import { SearchInput } from '@/components/data/SearchInput';
import { LoadingState, ErrorState } from '@/components/feedback';
import { HoverVideoPreview } from './HoverVideoPreview';
import { useMediaLibrary } from '../../hooks';
import type { MediaStaging } from '../../lib/useMediaStaging';
import type { MediaAssetData } from '@/types/content.types';

interface Props {
  staging: MediaStaging;
}

/** Browse + search the central library and stage existing assets for reuse. */
export function LibraryTab({ staging }: Props) {
  const { t } = useTranslation('content');
  const [search, setSearch] = useState('');
  const [type, setType] = useState<'' | 'image' | 'video'>('');

  const q = useMediaLibrary({ search, type, per_page: 24 });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <SearchInput
          value={search}
          onDebouncedChange={setSearch}
          placeholder={t('mediaStudio.library.search')}
        />
        <select
          className="h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          value={type}
          onChange={(e) => setType(e.target.value as '' | 'image' | 'video')}
        >
          <option value="">{t('mediaStudio.library.typeAll')}</option>
          <option value="image">{t('mediaStudio.library.typeImage')}</option>
          <option value="video">{t('mediaStudio.library.typeVideo')}</option>
        </select>
      </div>

      {q.isLoading ? (
        <LoadingState />
      ) : q.isError ? (
        <ErrorState onRetry={() => void q.refetch()} />
      ) : !q.data || q.data.data.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('mediaStudio.library.empty')}</p>
      ) : (
        <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
          {q.data.data.map((asset) => (
            <LibraryTile
              key={asset.id}
              asset={asset}
              staged={staging.hasAsset(asset.id)}
              onAdd={() => staging.addFromLibrary(asset)}
              addLabel={t('mediaStudio.library.add')}
              addedLabel={t('mediaStudio.library.added')}
            />
          ))}
        </div>
      )}
    </div>
  );
}

interface TileProps {
  asset: MediaAssetData;
  staged: boolean;
  onAdd: () => void;
  addLabel: string;
  addedLabel: string;
}

function LibraryTile({ asset, staged, onAdd, addLabel, addedLabel }: TileProps) {
  const isUploadedVideo = asset.is_video && !asset.is_external;
  const isVideo = asset.is_video || asset.is_external;
  const poster = asset.thumb ?? asset.poster ?? (asset.is_image ? asset.url : null);

  return (
    <figure className="group relative border border-border bg-background">
      <HoverVideoPreview
        className="aspect-square"
        poster={poster}
        videoSrc={asset.url}
        enabled={isUploadedVideo}
        alt={asset.original_name}
        showPlayIcon={isVideo}
        playIconSize="sm"
      />
      <button
        type="button"
        onClick={onAdd}
        disabled={staged}
        title={staged ? addedLabel : addLabel}
        className="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 bg-background/85 p-1 text-xs opacity-0 transition-opacity group-hover:opacity-100 disabled:opacity-100"
      >
        {staged ? (
          <>
            <Check className="h-3.5 w-3.5 text-primary" />
            {addedLabel}
          </>
        ) : (
          <>
            <Plus className="h-3.5 w-3.5" />
            {addLabel}
          </>
        )}
      </button>
    </figure>
  );
}
