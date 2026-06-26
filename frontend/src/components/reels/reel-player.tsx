'use client';

import { useEffect, useRef, useState } from 'react';

interface Props {
  hls: string | null;
  mp4: string | null;
  poster: string | null;
  active: boolean;
  muted: boolean;
}

// مشغّل ريل واحد: <video> + HLS (Safari أصليّ / hls.js كسوليّ / fallback MP4)،
// يشتغل عند التفعيل ويتوقّف عند الخروج، نقر للتبديل، شريط تقدّم + مؤشّر تحميل.
export function ReelPlayer({ hls, mp4, poster, active, muted }: Props) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const hlsRef = useRef<{ destroy: () => void } | null>(null);
  const [paused, setPaused] = useState(false);
  const [progress, setProgress] = useState(0);
  const [buffering, setBuffering] = useState(false);

  // إرفاق المصدر: HLS تكيّفيّ عبر hls.js (أو أصليّ في Safari)، وإلا MP4.
  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;
    let cancelled = false;

    async function setup() {
      if (!video) return;
      if (hls && video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = hls; // Safari/iOS — HLS أصليّ
        return;
      }
      if (hls) {
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
            return;
          }
        } catch {
          /* يسقط إلى MP4 */
        }
      }
      if (mp4) video.src = mp4;
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
  }, [hls, mp4]);

  // تشغيل/إيقاف حسب التفعيل.
  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;
    video.muted = muted;
    if (active) {
      video.play().catch(() => setPaused(true));
    } else {
      video.pause();
    }
  }, [active, muted]);

  function toggle() {
    const video = videoRef.current;
    if (!video) return;
    if (video.paused) video.play().catch(() => {});
    else video.pause();
  }

  return (
    <button type="button" className="absolute inset-0 cursor-default" onClick={toggle} aria-label="تشغيل/إيقاف">
      <video
        ref={videoRef}
        poster={poster ?? undefined}
        className="size-full object-cover"
        playsInline
        loop
        preload={active ? 'auto' : 'metadata'}
        onPlay={() => setPaused(false)}
        onPause={() => setPaused(true)}
        onTimeUpdate={(e) => {
          const v = e.currentTarget;
          if (v.duration && isFinite(v.duration)) setProgress((v.currentTime / v.duration) * 100);
        }}
        onWaiting={() => setBuffering(true)}
        onPlaying={() => setBuffering(false)}
      />

      {active && paused && (
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <span
            className="flex size-16 items-center justify-center bg-black/45 text-white backdrop-blur-sm"
            style={{ borderRadius: '9999px' }}
          >
            <svg viewBox="0 0 24 24" className="size-8" fill="currentColor" aria-hidden>
              <path d="M8 5v14l11-7z" />
            </svg>
          </span>
        </span>
      )}

      {active && buffering && !paused && (
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <span
            className="size-9 animate-spin border-[3px] border-white/25 border-t-white"
            style={{ borderRadius: '9999px' }}
          />
        </span>
      )}

      <span className="pointer-events-none absolute inset-x-0 bottom-0 h-[3px] bg-white/20">
        <span className="block h-full bg-primary" style={{ width: `${progress}%` }} />
      </span>
    </button>
  );
}
