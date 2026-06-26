import { useState } from 'react';
import { Film, Play } from 'lucide-react';
import type { MediaStaging } from '../lib/useMediaStaging';

export interface PreviewImage {
  id: number;
  url: string;
  thumb?: string | null;
  alt?: string | null;
}

export interface PreviewVideo {
  id: number;
  url: string | null;
  poster?: string | null;
  isExternal?: boolean;
  provider?: string | null;
}

/** Map client-staged media → polished preview props (shared by composer + card). */
export function stagingToPreview(staging: MediaStaging): {
  images: PreviewImage[];
  videos: PreviewVideo[];
} {
  const images: PreviewImage[] = [...(staging.cover ? [staging.cover] : []), ...staging.gallery].map(
    (i) => ({ id: i.assetId, url: i.url ?? '', thumb: i.thumb, alt: i.name }),
  );
  const videos: PreviewVideo[] = staging.videos.map((v) => ({
    id: v.assetId,
    url: v.url,
    poster: v.poster ?? null,
    isExternal: v.external,
    provider: v.provider ?? null,
  }));
  return { images, videos };
}

interface Props {
  images: PreviewImage[];
  videos: PreviewVideo[];
}

/**
 * Polished live-update media presentation — newsroom live-blog style.
 *
 * Images use smart collage layouts (1 / 2 / 3 / 4+); videos render as a compact
 * poster + play affordance that loads the player on demand (keeps the timeline
 * light). Design system: clean bordered frames, no border-radius.
 */
export function LiveUpdateMedia({ images, videos }: Props) {
  if (images.length === 0 && videos.length === 0) return null;

  return (
    <div className="mt-3 space-y-2">
      {images.length > 0 ? <ImageCollage images={images} /> : null}
      {videos.map((v) => (
        <VideoPreview key={v.id} video={v} />
      ))}
    </div>
  );
}

const src = (i: PreviewImage): string => i.thumb ?? i.url;

function ImageCollage({ images }: { images: PreviewImage[] }) {
  const n = images.length;

  if (n === 1) {
    const img = images[0];
    return (
      <figure className="overflow-hidden rounded-lg border border-border bg-muted/30">
        <img src={img.url} alt={img.alt ?? ''} className="max-h-[26rem] w-full object-cover" />
      </figure>
    );
  }

  if (n === 2) {
    return (
      <div className="grid grid-cols-2 gap-1">
        {images.map((i) => (
          <img
            key={i.id}
            src={src(i)}
            alt={i.alt ?? ''}
            className="aspect-[4/3] w-full rounded-lg border border-border object-cover"
          />
        ))}
      </div>
    );
  }

  if (n === 3) {
    const [a, b, c] = images;
    return (
      <div className="grid grid-cols-2 gap-1">
        <img
          src={src(a)}
          alt={a.alt ?? ''}
          className="row-span-2 h-full w-full rounded-lg border border-border object-cover"
        />
        <img src={src(b)} alt={b.alt ?? ''} className="aspect-[4/3] w-full rounded-lg border border-border object-cover" />
        <img src={src(c)} alt={c.alt ?? ''} className="aspect-[4/3] w-full rounded-lg border border-border object-cover" />
      </div>
    );
  }

  // 4+ → gallery grid, "+N" overlay on the last visible tile
  const shown = images.slice(0, 4);
  const extra = n - 4;
  return (
    <div className="grid grid-cols-2 gap-1">
      {shown.map((i, idx) => (
        <div key={i.id} className="relative overflow-hidden rounded-lg">
          <img
            src={src(i)}
            alt={i.alt ?? ''}
            className="aspect-square w-full rounded-lg border border-border object-cover"
          />
          {idx === 3 && extra > 0 ? (
            <div className="absolute inset-0 flex items-center justify-center bg-black/55 text-lg font-bold text-white">
              +{extra}
            </div>
          ) : null}
        </div>
      ))}
    </div>
  );
}

const IFRAME_ALLOW =
  'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';

function VideoPreview({ video }: { video: PreviewVideo }) {
  const [playing, setPlaying] = useState(false);

  if (playing && video.url) {
    return video.isExternal ? (
      <iframe
        src={video.url}
        title="video"
        className="aspect-video w-full max-w-2xl rounded-lg border border-border"
        allow={IFRAME_ALLOW}
        allowFullScreen
      />
    ) : (
      // eslint-disable-next-line jsx-a11y/media-has-caption
      <video src={video.url} controls autoPlay className="aspect-video w-full max-w-2xl rounded-lg border border-border bg-black" />
    );
  }

  return (
    <button
      type="button"
      onClick={() => setPlaying(true)}
      className="group relative block aspect-video w-full max-w-2xl overflow-hidden rounded-lg border border-border bg-black"
    >
      {video.poster ? (
        <img src={video.poster} alt="" className="h-full w-full object-cover opacity-90" />
      ) : (
        <span className="flex h-full w-full items-center justify-center">
          <Film className="h-8 w-8 text-white/40" />
        </span>
      )}
      <span className="absolute inset-0 flex items-center justify-center">
        <span className="flex h-12 w-12 items-center justify-center bg-black/60 text-white transition-colors group-hover:bg-primary">
          <Play className="h-5 w-5 fill-current" />
        </span>
      </span>
      {video.provider ? (
        <span className="absolute end-2 top-2 bg-black/60 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">
          {video.provider}
        </span>
      ) : null}
    </button>
  );
}
