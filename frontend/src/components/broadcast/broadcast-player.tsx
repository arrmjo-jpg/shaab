import { Radio as RadioIcon } from 'lucide-react';
import Link from 'next/link';

import { VideoPlayer } from '@/components/videos/video-player';
import type { BroadcastPlayback } from '@/lib/broadcast-types';
import { youtubeIdFrom } from '@/lib/broadcast-util';

import { BroadcastCountdown } from './broadcast-countdown';

// منتقي المشغّل حسب حالة/مصدر البثّ (يعيد استخدام VideoPlayer للـ hls/يوتيوب — لا منطق جديد):
//   live + youtube_live ⇒ VideoPlayer (iframe يوتيوب)؛ live + hls/iptv ⇒ VideoPlayer (hls.js كسوليّ)؛
//   live + icecast/shoutcast ⇒ مشغّل صوت؛ live + external_provider ⇒ iframe موثوق.
//   upcoming ⇒ بوستر + عدّ تنازليّ؛ ended ⇒ تسجيل VOD أو «انتهى»؛ offline/failed ⇒ «غير متاح».
// المشغّل لا يُحمَّل إلا على هذه الصفحة (lazy حسب الحالة) — hls.js يُستورَد ديناميكيّاً داخل VideoPlayer.
export function BroadcastPlayer({
  playback,
  poster,
  title,
}: {
  playback: BroadcastPlayback;
  poster: string | null;
  title: string;
}) {
  const { state, source } = playback;

  if (state === 'live' && source) {
    if (source.type === 'youtube_live') {
      const yid = youtubeIdFrom(source.url);
      if (yid) {
        return (
          <div className="aspect-video w-full bg-black">
            <VideoPlayer hls={null} mp4={null} youtubeId={yid} poster={poster} title={title} autoPlay />
          </div>
        );
      }
    }
    if (source.type === 'hls' || source.type === 'iptv') {
      return (
        <div className="aspect-video w-full bg-black">
          <VideoPlayer hls={source.url} mp4={null} youtubeId={null} poster={poster} title={title} autoPlay />
        </div>
      );
    }
    if (source.type === 'icecast' || source.type === 'shoutcast') {
      return (
        <div className="flex items-center gap-4 bg-[#14161b] p-6 text-white">
          <span className="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-primary">
            <RadioIcon className="size-6" aria-hidden />
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate font-bold">{title}</p>
            <audio src={source.url} controls autoPlay className="mt-2 w-full" />
          </div>
        </div>
      );
    }
    if (source.type === 'external_provider') {
      return (
        <div className="relative aspect-video w-full bg-black">
          <iframe
            src={source.url}
            title={title}
            className="absolute inset-0 size-full"
            allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
            allowFullScreen
          />
        </div>
      );
    }
  }

  return (
    <div className="relative flex aspect-video w-full items-center justify-center overflow-hidden bg-[#14161b] text-center text-white">
      {poster ? (
        // eslint-disable-next-line @next/next/no-img-element -- بوستر حالة غير المباشر (خلفيّة خافتة)
        <img src={poster} alt="" aria-hidden className="absolute inset-0 size-full object-cover opacity-35" />
      ) : null}
      <div className="relative z-10 px-6">
        {state === 'upcoming' && playback.startsAt ? (
          <>
            <p className="text-sm text-white/80">يبدأ البثّ بعد</p>
            <BroadcastCountdown target={playback.startsAt} className="mt-1 block text-3xl font-extrabold" />
          </>
        ) : state === 'ended' && playback.vod ? (
          <Link href={playback.vod.href} className="inline-flex items-center gap-2 bg-white px-4 py-2 text-sm font-bold text-black">
            شاهد التسجيل
          </Link>
        ) : state === 'ended' ? (
          <p className="text-lg font-bold">انتهى البثّ</p>
        ) : state === 'offline' ? (
          <p className="text-lg font-bold">البثّ متوقّف مؤقّتاً</p>
        ) : (
          <p className="text-lg font-bold">غير متاح حالياً</p>
        )}
      </div>
    </div>
  );
}
