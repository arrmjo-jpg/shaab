import { ListVideo } from 'lucide-react';

import { VideoListItem } from './video-list-item';
import type { PlaylistItem } from '@/lib/videos';

// لوحة قائمة التشغيل في صفحة المشاهدة — رأس (eyebrow + عنوان + موضع الحاليّ/الإجماليّ) + قائمة قابلة للتمرير،
// الفيديو الحاليّ مميَّز. التنقّل: روابط العناصر تحمل سياق `?playlist=` (تُمرَّر من الصفحة عبر href). مُقدِّميّ بحت؛
// مربّع/لوجيّ/tokens. (يُعرَض فقط عند وجود قائمة فعليّة — لا تلفيق عضويّة.)
export function WatchPlaylist({ playlist, currentId }: { playlist: PlaylistItem; currentId: number }) {
  const index = playlist.videos.findIndex((v) => v.id === currentId);
  const position = index >= 0 ? index + 1 : null;

  return (
    <section aria-label={`قائمة التشغيل: ${playlist.title}`} className="border border-border">
      <div className="border-b border-border bg-surface-2 p-3">
        <div className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-muted">
          <ListVideo className="size-3.5 shrink-0" aria-hidden />
          قائمة تشغيل
        </div>
        <h2 className="mt-1 line-clamp-1 text-base font-extrabold text-fg">{playlist.title}</h2>
        <p className="mt-0.5 text-caption tabular-nums text-muted">
          {position ? `${position} / ${playlist.videosCount}` : `${playlist.videosCount} فيديو`}
        </p>
      </div>
      <div className="max-h-[28rem] overflow-y-auto p-1.5">
        {playlist.videos.map((v) => (
          <VideoListItem key={v.id} video={v} active={v.id === currentId} />
        ))}
      </div>
    </section>
  );
}
