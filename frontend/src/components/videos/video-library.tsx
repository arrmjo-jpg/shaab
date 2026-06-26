'use client';

import { Play } from 'lucide-react';
import { useState } from 'react';

import { VideoPlayer } from '@/components/videos/video-player';
import type { VideoItem } from '@/lib/videos';

// مكتبة الفيديو التفاعليّة (عميل) — لوحة داكنة فاخرة: مشغّل وسطيّ كبير + مصغّرات يمين/يسار (سطح المكتب)
// أو شريط أفقيّ (الجوّال). النقر على مصغّرة ⇒ تشغيلها بالنصّ فوراً (تبديل المصدر + play، إعادة تركيب المشغّل
// عبر key فيُنظَّف hls.js). المصغّرة الفعّالة مميّزة بإطار أحمر.
export function VideoLibrary({ videos }: { videos: VideoItem[] }) {
  const [activeId, setActiveId] = useState<number | null>(videos[0]?.id ?? null);
  const [autoPlay, setAutoPlay] = useState(false);

  const active = videos.find((v) => v.id === activeId) ?? videos[0];
  if (!active) return null;

  const half = Math.ceil(videos.length / 2);
  const right = videos.slice(0, half);
  const left = videos.slice(half);

  const select = (id: number) => {
    setActiveId(id);
    setAutoPlay(true);
  };

  return (
    <div dir="rtl" className="overflow-hidden" style={{ borderRadius: 18, backgroundImage: 'linear-gradient(160deg,#0e1525,#0a0f1c)' }}>
      <div className="flex flex-col gap-4 p-4 lg:flex-row">
        {/* مصغّرات يمين (سطح المكتب) */}
        <ThumbColumn items={right} activeId={active.id} onSelect={select} />

        {/* المشغّل الوسطيّ */}
        <div className="order-first min-w-0 flex-1 lg:order-none">
          <div className="aspect-[16/9] w-full overflow-hidden bg-black" style={{ borderRadius: 12 }}>
            <VideoPlayer
              key={active.id}
              hls={active.hls}
              mp4={active.mp4}
              youtubeId={active.youtubeId}
              poster={active.poster}
              title={active.title}
              autoPlay={autoPlay}
            />
          </div>
          <h3 className="mt-3 line-clamp-2 text-base font-bold leading-snug text-white sm:text-lg">{active.title}</h3>
        </div>

        {/* مصغّرات يسار (سطح المكتب) */}
        <ThumbColumn items={left} activeId={active.id} onSelect={select} />

        {/* شريط مصغّرات أفقيّ (الجوّال) */}
        <div className="flex gap-3 overflow-x-auto pb-1 lg:hidden [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
          {videos.map((v) => (
            <div key={v.id} className="w-[58%] shrink-0">
              <Thumb item={v} active={v.id === active.id} onSelect={select} />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function ThumbColumn({ items, activeId, onSelect }: { items: VideoItem[]; activeId: number; onSelect: (id: number) => void }) {
  if (items.length === 0) return null;
  return (
    <div className="hidden w-[230px] shrink-0 flex-col gap-3 lg:flex">
      {items.map((v) => (
        <Thumb key={v.id} item={v} active={v.id === activeId} onSelect={onSelect} />
      ))}
    </div>
  );
}

function Thumb({ item, active, onSelect }: { item: VideoItem; active: boolean; onSelect: (id: number) => void }) {
  return (
    <button
      type="button"
      onClick={() => onSelect(item.id)}
      aria-label={item.title}
      aria-current={active}
      className={`group relative block w-full overflow-hidden transition ${active ? 'ring-2 ring-primary' : 'ring-1 ring-white/10 hover:ring-white/30'}`}
      style={{ borderRadius: 10 }}
    >
      <div className="relative aspect-[16/9] w-full bg-white/5">
        {item.poster ? (
          // eslint-disable-next-line @next/next/no-img-element -- <img> مقصود: حارس أداء الهوم
          <img
            src={item.poster}
            alt={item.title}
            loading="lazy"
            decoding="async"
            className="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-105 motion-reduce:group-hover:scale-100"
          />
        ) : (
          <div className="size-full" aria-hidden />
        )}
        <span className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-transparent" aria-hidden />
        <span className="absolute inset-0 flex items-center justify-center">
          <span
            className={`flex size-9 items-center justify-center bg-primary/90 text-white transition ${active ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'}`}
            style={{ borderRadius: 9999 }}
          >
            <Play className="size-4" fill="currentColor" aria-hidden />
          </span>
        </span>
        {item.durationLabel && (
          <span className="absolute left-1.5 top-1.5 bg-black/70 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-white" style={{ borderRadius: 4 }}>
            {item.durationLabel}
          </span>
        )}
        <span className="absolute inset-x-0 bottom-0 line-clamp-2 px-2 pb-1.5 text-right text-[11px] font-bold leading-snug text-white">
          {item.title}
        </span>
      </div>
    </button>
  );
}
