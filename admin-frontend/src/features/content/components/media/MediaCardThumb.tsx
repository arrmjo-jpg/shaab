import { useTranslation } from 'react-i18next';
import { HoverVideoPreview } from './HoverVideoPreview';
import type { MediaAssetData } from '@/types/content.types';

function formatDuration(seconds: number | null): string | null {
  if (!seconds) return null;
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${m}:${s.toString().padStart(2, '0')}`;
}

interface Props {
  asset: MediaAssetData;
}

/**
 * Library tile media area: poster/thumbnail with a hover-to-preview muted loop
 * for ready uploaded videos, plus duration / source / play overlays. External
 * videos fall back to their provider poster (no inline autoplay).
 */
export function MediaCardThumb({ asset }: Props) {
  const { t } = useTranslation('content');

  const isUploadedVideo = asset.is_video && !asset.is_external;
  const isVideo = asset.is_video || asset.is_external;
  const duration = formatDuration(asset.duration);

  // للفيديو: poster (إطار مُستخرَج، صورة صحيحة) أولاً — thumb قد يكون رابط الفيديو نفسه
  // لا صورة. للصورة: thumb ثم الأصل.
  const poster = isVideo
    ? (asset.poster ?? asset.thumb ?? null)
    : (asset.thumb ?? asset.poster ?? (asset.is_image ? asset.url : null));

  return (
    <HoverVideoPreview
      className="aspect-square"
      poster={poster}
      videoSrc={asset.url}
      // المعاينة تشغّل الملف المرفوع الأصلي مباشرةً (مستقلّ عن تحويل HLS) — تُتاح
      // لأي فيديو مرفوع له ملف، عدا الخارجي واللمس (يُحكَمان داخل المكوّن).
      enabled={isUploadedVideo}
      alt={asset.alt ?? asset.original_name}
      showPlayIcon={isVideo}
    >
      {isVideo ? (
        <span className="pointer-events-none absolute inset-x-0 bottom-0 flex items-end justify-between gap-1 bg-gradient-to-t from-foreground/70 to-transparent p-1.5">
          <span className="rounded bg-background/20 px-1 text-[10px] font-medium text-background backdrop-blur-sm">
            {asset.is_external
              ? (asset.provider ?? t('mediaLibrary.kind.external'))
              : t('mediaLibrary.preview.uploaded')}
          </span>
          {duration ? (
            <span
              className="rounded bg-background/20 px-1 text-[10px] font-medium text-background backdrop-blur-sm"
              dir="ltr"
            >
              {duration}
            </span>
          ) : null}
        </span>
      ) : null}
    </HoverVideoPreview>
  );
}
