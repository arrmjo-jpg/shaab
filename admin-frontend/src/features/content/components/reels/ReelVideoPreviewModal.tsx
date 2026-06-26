import { useQuery } from '@tanstack/react-query';
import { mediaLibraryService } from '@/services/mediaLibrary.service';
import { ReelVideoPlayer } from './ReelVideoPlayer';

interface Props {
  /** Media asset uuid to preview; null = closed. */
  uuid: string | null;
  title: string;
  onClose: () => void;
}

/**
 * يجلب أصل الوسائط بالـ uuid عند الطلب (من الجدول حيث لا تتوفّر روابط النسخ)
 * ثم يعرضه عبر ReelVideoPlayer. يُعيد استخدام mediaLibraryService القائم.
 */
export function ReelVideoPreviewModal({ uuid, title, onClose }: Props) {
  const q = useQuery({
    queryKey: ['media', 'preview', uuid],
    queryFn: () => mediaLibraryService.get(uuid as string),
    enabled: uuid !== null,
  });

  const asset = q.data;
  const src = asset?.renditions?.master ?? asset?.renditions?.['720p'] ?? null;
  const poster = asset?.thumbnail?.jpg ?? asset?.poster ?? null;

  return (
    <ReelVideoPlayer
      open={uuid !== null}
      onClose={onClose}
      title={title}
      src={uuid !== null ? src : null}
      poster={poster}
      loading={q.isLoading}
    />
  );
}
