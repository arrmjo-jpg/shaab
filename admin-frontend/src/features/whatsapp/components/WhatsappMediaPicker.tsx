import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { mediaLibraryService } from '@/services/mediaLibrary.service';
import type { MediaAssetData } from '@/types/content.types';

interface Props {
  kind: 'image' | 'video';
  selectedId: number | null;
  onSelect: (asset: { id: number; url: string | null } | null) => void;
}

/** منتقي وسيط من المكتبة المركزية — بحث + اختيار صورة/فيديو واحد لحملة إعلانية. */
export function WhatsappMediaPicker({ kind, selectedId, onSelect }: Props) {
  const { t } = useTranslation('whatsapp');
  const [search, setSearch] = useState('');

  const q = useQuery({
    queryKey: ['whatsapp', 'media-picker', kind, search],
    queryFn: () => mediaLibraryService.list({ type: kind, search, page: 1, per_page: 12 }),
  });

  const assets = q.data?.data ?? [];

  return (
    <div className="space-y-2">
      <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('campaigns.form.mediaSearch')} />
      <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
        {assets.map((a: MediaAssetData) => {
          const isSel = a.id === selectedId;
          return (
            <button
              key={a.id}
              type="button"
              onClick={() => onSelect(isSel ? null : { id: a.id, url: a.url })}
              className={`relative aspect-square overflow-hidden border ${isSel ? 'border-primary ring-2 ring-primary' : 'border-border'}`}
            >
              {a.thumb || a.url ? (
                <img src={a.thumb ?? a.url ?? ''} alt="" className="h-full w-full object-cover" />
              ) : (
                <span className="flex h-full items-center justify-center text-xs text-muted-foreground">{kind}</span>
              )}
            </button>
          );
        })}
      </div>
      {assets.length === 0 && !q.isLoading ? (
        <p className="text-sm text-muted-foreground">{t('campaigns.form.noMedia')}</p>
      ) : null}
    </div>
  );
}
