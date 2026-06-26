import { Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';

interface Props {
  open: boolean;
  onClose: () => void;
  title: string;
  /** MP4 playback URL (rendition master). null → unavailable/loading. */
  src: string | null;
  poster?: string | null;
  loading?: boolean;
}

/**
 * مشغّل فيديو الريل (لوحة الإدارة) — نافذة بمشغّل HTML5 يعرض نسخة MP4 الجاهزة
 * (renditions.master). عمودي الاتجاه (9:16) ومحدود الارتفاع داخل النافذة.
 */
export function ReelVideoPlayer({ open, onClose, title, src, poster, loading }: Props) {
  const { t } = useTranslation('content');

  return (
    <Modal open={open} onClose={onClose} title={title} size="md">
      <div className="flex min-h-[40vh] items-center justify-center bg-black">
        {loading ? (
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        ) : src ? (
          <video
            key={src}
            src={src}
            poster={poster ?? undefined}
            controls
            autoPlay
            playsInline
            preload="metadata"
            className="max-h-[60vh] w-auto"
          />
        ) : (
          <p className="px-6 py-12 text-center text-sm text-muted-foreground">
            {t('reels.player.unavailable')}
          </p>
        )}
      </div>
    </Modal>
  );
}
