import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Image, Film, Library } from 'lucide-react';
import { ImagesTab } from './ImagesTab';
import { VideoTab } from './VideoTab';
import { LibraryTab } from './LibraryTab';
import type { MediaStaging } from '../../lib/useMediaStaging';

type Tab = 'images' | 'video' | 'library';

interface Props {
  staging: MediaStaging;
}

/**
 * Unified Media Studio — a single workspace for all article media, backed by
 * the central shared-asset library and client-stage attach-on-save. No article
 * id required: assets upload to the library immediately and attach when the
 * article is saved.
 */
export function MediaStudio({ staging }: Props) {
  const { t } = useTranslation('content');
  const [tab, setTab] = useState<Tab>('images');

  const tabs: Array<{ key: Tab; label: string; icon: typeof Image }> = [
    { key: 'images', label: t('mediaStudio.tabs.images'), icon: Image },
    { key: 'video', label: t('mediaStudio.tabs.video'), icon: Film },
    { key: 'library', label: t('mediaStudio.tabs.library'), icon: Library },
  ];

  return (
    <div className="space-y-4">
      <div className="flex border border-border">
        {tabs.map((tb) => {
          const Icon = tb.icon;
          const active = tab === tb.key;
          return (
            <button
              key={tb.key}
              type="button"
              onClick={() => setTab(tb.key)}
              className={[
                'flex flex-1 items-center justify-center gap-2 px-3 py-2 text-sm font-medium transition-colors',
                active
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-background text-muted-foreground hover:text-foreground',
              ].join(' ')}
            >
              <Icon className="h-4 w-4" />
              {tb.label}
            </button>
          );
        })}
      </div>

      {tab === 'images' ? <ImagesTab staging={staging} /> : null}
      {tab === 'video' ? <VideoTab staging={staging} /> : null}
      {tab === 'library' ? <LibraryTab staging={staging} /> : null}
    </div>
  );
}
