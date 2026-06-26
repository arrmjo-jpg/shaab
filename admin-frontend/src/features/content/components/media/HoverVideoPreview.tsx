import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Film, Play } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Desktop pointers only — touch devices report `(hover: none)`, so we never
 * autoplay there (mobile data + accidental triggers).
 */
export const SUPPORTS_HOVER =
  typeof window !== 'undefined' &&
  typeof window.matchMedia === 'function' &&
  window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/**
 * Newsroom-DAM rule: only one preview plays at a time. A module-level handle
 * pauses + rewinds the previously-playing clip the moment another starts.
 */
let activePreview: HTMLVideoElement | null = null;

function playExclusive(el: HTMLVideoElement): void {
  if (activePreview && activePreview !== el) {
    activePreview.pause();
    activePreview.currentTime = 0;
  }
  activePreview = el;
  void el.play().catch(() => {
    /* autoplay can be blocked mid-gesture — ignore, poster stays visible */
  });
}

function stopPreview(el: HTMLVideoElement): void {
  el.pause();
  el.currentTime = 0;
  if (activePreview === el) activePreview = null;
}

interface Props {
  /** Still image shown at rest (poster / thumbnail). */
  poster: string | null;
  /** Muted-loop source. Preview is disabled when null/empty. */
  videoSrc?: string | null;
  /**
   * Caller-side gate (e.g. uploaded + ready). Combined with `SUPPORTS_HOVER`
   * and a non-empty `videoSrc` to decide whether hover playback runs.
   */
  enabled?: boolean;
  fit?: 'cover' | 'contain';
  /** Center play affordance — hidden automatically while previewing. */
  showPlayIcon?: boolean;
  playIconSize?: 'sm' | 'md';
  /** Shown when there is no poster image. */
  fallback?: ReactNode;
  /** Container classes — set aspect/size here. */
  className?: string;
  imgClassName?: string;
  /** Extra overlays (badges, etc.) rendered above the media. */
  children?: ReactNode;
  alt?: string;
}

/**
 * Reusable poster-with-hover-video preview tile. Encapsulates the
 * exclusive-playback controller, touch guard, opacity crossfade and the
 * (optional) center play icon so every media surface behaves identically.
 */
export function HoverVideoPreview({
  poster,
  videoSrc,
  enabled = false,
  fit = 'cover',
  showPlayIcon = true,
  playIconSize = 'md',
  fallback,
  className,
  imgClassName,
  children,
  alt = '',
}: Props) {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const [previewing, setPreviewing] = useState(false);

  const canPreview = SUPPORTS_HOVER && enabled && !!videoSrc;
  const objectFit = fit === 'contain' ? 'object-contain' : 'object-cover';

  useEffect(() => {
    const el = videoRef.current;
    return () => {
      if (el) stopPreview(el);
    };
  }, []);

  const onEnter = () => {
    const el = videoRef.current;
    if (!canPreview || !el) return;
    setPreviewing(true);
    playExclusive(el);
  };

  const onLeave = () => {
    const el = videoRef.current;
    if (!canPreview || !el) return;
    setPreviewing(false);
    stopPreview(el);
  };

  const iconBox = playIconSize === 'sm' ? 'h-7 w-7' : 'h-9 w-9';
  const iconGlyph = playIconSize === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4';

  return (
    <div
      className={cn(
        'relative flex items-center justify-center overflow-hidden bg-muted/30',
        className,
      )}
      onMouseEnter={onEnter}
      onMouseLeave={onLeave}
    >
      {poster ? (
        <img
          src={poster}
          alt={alt}
          loading="lazy"
          className={cn('h-full w-full', objectFit, imgClassName)}
        />
      ) : (
        (fallback ?? <Film className="h-6 w-6 text-muted-foreground" />)
      )}

      {canPreview ? (
        <video
          ref={videoRef}
          src={videoSrc ?? undefined}
          poster={poster ?? undefined}
          muted
          loop
          playsInline
          preload="none"
          className={cn(
            'absolute inset-0 h-full w-full transition-opacity duration-200',
            objectFit,
            previewing ? 'opacity-100' : 'opacity-0',
          )}
        />
      ) : null}

      {showPlayIcon && !previewing ? (
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <span
            className={cn(
              'flex items-center justify-center rounded-full bg-foreground/55 text-background backdrop-blur-sm',
              iconBox,
            )}
          >
            <Play className={cn('translate-x-px fill-current', iconGlyph)} />
          </span>
        </span>
      ) : null}

      {children}
    </div>
  );
}
