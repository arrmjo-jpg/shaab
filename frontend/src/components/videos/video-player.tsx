'use client';

import { useEffect, useRef, useState } from 'react';

interface Props {
  hls: string | null;
  mp4: string | null;
  youtubeId: string | null;
  poster: string | null;
  title: string;
  autoPlay: boolean;
}

// مشغّل فيديو فاخر — <video> بأدوات تحكّم أصليّة كاملة (تشغيل/تمرير/صوت/ملء شاشة/PiP) + HLS تكيّفيّ
// (Safari أصليّ / hls.js كسوليّ مُحمَّل عند الحاجة فقط / fallback MP4) + بوستر + زرّ تشغيل كبير. يوتيوب
// (مصدر خارجيّ) ⇒ iframe. يُعاد تركيبه عبر key عند تبديل الفيديو فيُنظَّف hls.js تلقائيّاً.
export function VideoPlayer({ hls, mp4, youtubeId, poster, title, autoPlay }: Props) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const hlsRef = useRef<{ destroy: () => void } | null>(null);
  const [started, setStarted] = useState(autoPlay);

  useEffect(() => {
    if (youtubeId) return; // اليوتيوب عبر iframe
    const video = videoRef.current;
    if (!video) return;
    let cancelled = false;

    async function setup() {
      if (!video) return;
      if (hls && video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = hls; // Safari/iOS — HLS أصليّ
      } else if (hls) {
        try {
          const Hls = (await import('hls.js')).default;
          if (cancelled || !video) return;
          if (Hls.isSupported()) {
            const inst = new Hls({ enableWorker: true });
            hlsRef.current = inst;
            inst.loadSource(hls);
            inst.attachMedia(video);
            inst.on(Hls.Events.ERROR, (_evt, data) => {
              if (data.fatal) {
                try {
                  inst.destroy();
                } catch {
                  /* noop */
                }
                hlsRef.current = null;
                if (mp4) video.src = mp4;
              }
            });
          } else if (mp4) {
            video.src = mp4;
          }
        } catch {
          if (mp4) video.src = mp4;
        }
      } else if (mp4) {
        video.src = mp4;
      }
      if (autoPlay) video.play().catch(() => {});
    }

    setup();
    return () => {
      cancelled = true;
      if (hlsRef.current) {
        try {
          hlsRef.current.destroy();
        } catch {
          /* noop */
        }
        hlsRef.current = null;
      }
    };
  }, [hls, mp4, youtubeId, autoPlay]);

  if (youtubeId) {
    return (
      <iframe
        className="size-full"
        src={`https://www.youtube-nocookie.com/embed/${youtubeId}?rel=0&playsinline=1${autoPlay ? '&autoplay=1' : ''}`}
        title={title}
        loading="lazy"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowFullScreen
      />
    );
  }

  return (
    <div className="relative size-full bg-black">
      <video
        ref={videoRef}
        controls
        poster={poster ?? undefined}
        className="size-full bg-black object-contain"
        playsInline
        preload="metadata"
        onPlay={() => setStarted(true)}
      />
      {!started && (
        <button
          type="button"
          onClick={() => {
            videoRef.current?.play().catch(() => {});
            setStarted(true);
          }}
          className="absolute inset-0 flex items-center justify-center bg-black/25 transition hover:bg-black/40"
          aria-label="تشغيل الفيديو"
        >
          <span
            className="flex size-16 items-center justify-center bg-primary text-white shadow-lg transition-transform hover:scale-105"
            style={{ borderRadius: 9999 }}
          >
            <svg viewBox="0 0 24 24" className="size-8" fill="currentColor" aria-hidden>
              <path d="M8 5v14l11-7z" />
            </svg>
          </span>
        </button>
      )}
    </div>
  );
}
