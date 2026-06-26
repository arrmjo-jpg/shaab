import { ListVideo, Play } from 'lucide-react';
import Link from 'next/link';

import { SectionHeader } from './section-header';
import { VideoRail } from './video-rail';
import type { PlaylistItem } from '@/lib/videos';

// كاروسيل قائمة تشغيل (premium) — ترويسة (عنوان + عدد + إجماليّ مدّة + «عرض الكل») ثمّ رفّ يبدأ ببطاقة غلاف القائمة
// («تشغيل الكل» + عدد + مدّة) تليها الفيديوهات المرتّبة (S1). مُقدِّميّ بحت (props، لا hardcoding). المدّة من مدد
// الأعضاء الحقيقيّة (null لو غابت). فارغ/بلا أعضاء ⇒ null (حالة فارغة صادقة).
export function PlaylistRail({ playlist, id }: { playlist: PlaylistItem | null; id?: string }) {
  if (!playlist || playlist.videos.length === 0) return null;

  const totalSeconds = playlist.videos.reduce((sum, v) => sum + (v.durationSeconds ?? 0), 0);
  const durationLabel = formatTotalDuration(totalSeconds);

  // روابط الفيديوهات تحمل سياق القائمة (?playlist) ليظهر Sidebar القائمة في صفحة المشاهدة (نمط YouTube ?list).
  const railVideos = playlist.videos.map((v) => ({
    ...v,
    href: `${v.href}${v.href.includes('?') ? '&' : '?'}playlist=${encodeURIComponent(playlist.slug)}`,
  }));
  // «تشغيل الكل»/«عرض الكل» يبدآن القائمة من أوّل فيديو بسياقها (لا توجد صفحة فهرس قائمة منفصلة بعد).
  const openHref = railVideos[0]?.href ?? playlist.href;

  const meta = (
    <>
      <span className="inline-flex items-center gap-1">
        <ListVideo className="size-3.5 shrink-0" aria-hidden />
        {playlist.videosCount} فيديو
      </span>
      {durationLabel && (
        <>
          <span aria-hidden>·</span>
          <span className="tabular-nums">{durationLabel}</span>
        </>
      )}
    </>
  );

  const coverCard = (
    <Link
      href={openHref}
      aria-label={playlist.title}
      className="group/cover relative block aspect-video w-[80%] shrink-0 overflow-hidden bg-ink text-white sm:w-[44%] md:w-[300px]"
    >
      {playlist.cover ? (
        // eslint-disable-next-line @next/next/no-img-element -- <img> مقصود: حارس أداء
        <img
          src={playlist.cover}
          alt=""
          loading="lazy"
          decoding="async"
          className="size-full object-cover opacity-80 transition-transform duration-500 ease-out group-hover/cover:scale-105 motion-reduce:group-hover/cover:scale-100"
        />
      ) : (
        <div className="size-full bg-surface-3" aria-hidden />
      )}
      <span className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 to-black/30" aria-hidden />
      <span className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 text-center">
        <span
          className="flex size-12 items-center justify-center bg-primary text-white shadow-lg transition group-hover/cover:scale-105 motion-reduce:transition-none"
          style={{ borderRadius: 9999 }}
        >
          <Play className="size-5 translate-x-px" fill="currentColor" aria-hidden />
        </span>
        <span className="text-sm font-bold">تشغيل الكل</span>
        <span className="text-caption tabular-nums text-white/80">
          {playlist.videosCount} فيديو{durationLabel ? ` · ${durationLabel}` : ''}
        </span>
      </span>
    </Link>
  );

  return (
    <section aria-labelledby={id}>
      <SectionHeader title={playlist.title} id={id} eyebrow="قائمة تشغيل" meta={meta} viewAllHref={openHref} />
      <VideoRail items={railVideos} leadingCard={coverCard} />
    </section>
  );
}

// إجماليّ مدّة بصيغة عربيّة مختصرة («٤٥ دقيقة» / «١ س ٢٠ د»). صفر/غير معروف ⇒ null.
function formatTotalDuration(seconds: number): string | null {
  if (!seconds || seconds <= 0) return null;
  const h = Math.floor(seconds / 3600);
  const m = Math.round((seconds % 3600) / 60);
  if (h > 0) return `${h} س ${m} د`;
  return `${m} دقيقة`;
}
