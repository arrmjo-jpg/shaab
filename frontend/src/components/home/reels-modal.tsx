'use client';

import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { ReelPlayer } from '@/components/reels/reel-player';
import { formatNumber, formatRelativeTime } from '@/lib/format';
import type { ReelItem } from '@/lib/reels';
import { useEngagement } from '@/lib/use-engagement';

const CIRCLE = { borderRadius: '9999px' } as const;

// موديل ريلز في الرئيسية: مشغّل HLS + تنقّل (أسهم/عجلة/مفاتيح/لمس) + إعجاب/حفظ/مشاركة.
// يُحقن في body (Portal) ليغطّي الصفحة دون قصّ من الحاوية.
export function ReelsModal({
  items,
  index,
  onIndex,
  onClose,
  siteName,
  logo,
}: {
  items: ReelItem[];
  index: number;
  onIndex: (i: number) => void;
  onClose: () => void;
  siteName: string;
  logo: string | null;
}) {
  const [muted, setMuted] = useState(false);
  const reel = items[index];
  const touchY = useRef(0);
  const wheelLock = useRef(false);

  const canPrev = index > 0;
  const canNext = index < items.length - 1;

  // قفل تمرير الصفحة أثناء فتح الموديل.
  useEffect(() => {
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = '';
    };
  }, []);

  // تنقّل بالمفاتيح: Esc إغلاق، الأسهم تقليب.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose();
      else if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
        e.preventDefault();
        if (index < items.length - 1) onIndex(index + 1);
      } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
        e.preventDefault();
        if (index > 0) onIndex(index - 1);
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [index, items.length, onIndex, onClose]);

  // التفاعل انتقل إلى ReelEngagementRail المركزيّ (مُفتَّح بـkey=reel.id فيُهيَّأ عند التبديل).

  function onWheel(e: React.WheelEvent) {
    if (wheelLock.current || Math.abs(e.deltaY) < 24) return;
    wheelLock.current = true;
    if (e.deltaY > 0 && canNext) onIndex(index + 1);
    else if (e.deltaY < 0 && canPrev) onIndex(index - 1);
    window.setTimeout(() => {
      wheelLock.current = false;
    }, 550);
  }

  if (typeof document === 'undefined') return null;

  return createPortal(
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center"
      role="dialog"
      aria-modal="true"
      aria-label="عارض الريلز"
      onWheel={onWheel}
      onTouchStart={(e) => {
        touchY.current = e.touches[0].clientY;
      }}
      onTouchEnd={(e) => {
        const dy = touchY.current - e.changedTouches[0].clientY;
        if (Math.abs(dy) > 50) {
          if (dy > 0 && canNext) onIndex(index + 1);
          else if (dy < 0 && canPrev) onIndex(index - 1);
        }
      }}
    >
      <div className="absolute inset-0 bg-black/90 backdrop-blur-md" onClick={onClose} aria-hidden />

      {/* أسهم التنقّل (سطح المكتب) — أعلى/أسفل */}
      <div className="absolute end-3 top-1/2 z-30 hidden -translate-y-1/2 flex-col gap-3 sm:flex">
        <NavArrow dir="up" disabled={!canPrev} onClick={() => canPrev && onIndex(index - 1)} />
        <NavArrow dir="down" disabled={!canNext} onClick={() => canNext && onIndex(index + 1)} />
      </div>

      {/* البطاقة 9:16 */}
      <div
        className="relative z-10 aspect-[9/16] h-[92vh] max-h-[92vh] max-w-[94vw] overflow-hidden bg-black shadow-2xl"
        style={{ borderRadius: '16px' }}
      >
        <div className="absolute inset-0 overflow-hidden" style={{ borderRadius: '16px' }}>
          <ReelPlayer key={reel.id} hls={reel.hls} mp4={reel.mp4} poster={reel.poster} active muted={muted} />
        </div>

        <div
          className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-black/70 to-transparent"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-black/85 to-transparent"
          aria-hidden
        />

        {/* الشعار أعلى اليمين (علامة مائيّة، لا تلتقط النقر فلا تؤثّر على الأزرار) */}
        {logo && (
          <span className="pointer-events-none absolute start-3 top-3 z-20">
            {/* eslint-disable-next-line @next/next/no-img-element -- شعار العلامة */}
            <img src={logo} alt="" className="h-7 w-auto object-contain opacity-90 drop-shadow-md" />
          </span>
        )}

        {/* التحكّم أعلى اليسار (بعيداً عن الشعار): إغلاق + كتم */}
        <div className="absolute end-3 top-3 z-20 flex items-center gap-2">
          <button
            type="button"
            onClick={onClose}
            aria-label="إغلاق"
            className="flex size-9 items-center justify-center bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20"
            style={CIRCLE}
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="size-5" aria-hidden>
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <button
            type="button"
            onClick={() => setMuted((m) => !m)}
            aria-label={muted ? 'تشغيل الصوت' : 'كتم الصوت'}
            className="flex size-9 items-center justify-center bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20"
            style={CIRCLE}
          >
            {muted ? (
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} className="size-5" aria-hidden>
                <path strokeLinecap="round" strokeLinejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15zM17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
              </svg>
            ) : (
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} className="size-5" aria-hidden>
                <path strokeLinecap="round" strokeLinejoin="round" d="M15.536 8.464a5 5 0 010 7.072M17.95 6.05a8.963 8.963 0 010 11.9M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
              </svg>
            )}
          </button>
        </div>

        {/* الميتا (بداية/يمين) */}
        <div className="absolute bottom-0 z-20 max-w-[75%] p-4 text-white" style={{ insetInlineStart: 0 }}>
          <div className="mb-2 flex items-center gap-2">
            <span
              className="flex size-7 items-center justify-center bg-primary text-xs font-black text-primary-foreground"
              style={{ borderRadius: '8px' }}
              aria-hidden
            >
              {siteName.slice(0, 1)}
            </span>
            <span className="text-sm font-bold drop-shadow">{siteName}</span>
          </div>
          <h2 className="mb-1 line-clamp-2 text-base font-bold leading-snug drop-shadow">{reel.title}</h2>
          {reel.description && (
            <p className="mb-1 line-clamp-2 text-sm text-white/85 drop-shadow">{reel.description}</p>
          )}
          <div className="flex items-center gap-2 text-xs text-white/70">
            {reel.publishedAt && <span>{formatRelativeTime(reel.publishedAt)}</span>}
            {reel.metrics.views > 0 && (
              <>
                <span aria-hidden>•</span>
                <span>{formatNumber(reel.metrics.views)} مشاهدة</span>
              </>
            )}
          </div>
        </div>

        {/* شريط التفاعل (نهاية/يسار) — مركزيّ، مُفتَّح بـkey=reel.id ليُحدَّث عند التبديل */}
        <ReelEngagementRail key={reel.id} reel={reel} />
      </div>
    </div>,
    document.body,
  );
}

// شريط تفاعل الريل (Consumer لنظام Engagement العام) — يُعاد تركيبه عند تبديل الريل (key=reel.id)
// فتُهيَّأ الحالة من جديد. منطق الإعجاب/الحفظ كلّه في useEngagement (صفر تكرار).
function ReelEngagementRail({ reel }: { reel: ReelItem }) {
  const { metrics, reaction, favorited, react, toggleFavorite } = useEngagement({
    type: 'reel',
    id: reel.id,
    initialMetrics: {
      views: reel.metrics.views,
      likes: reel.metrics.likes,
      dislikes: 0,
      favorites: reel.metrics.favorites,
    },
  });
  const liked = reaction === 'like';

  async function share() {
    const url = `${window.location.origin}${reel.href}`;
    if (navigator.share) {
      try {
        await navigator.share({ title: reel.title, url });
      } catch {
        /* ألغى */
      }
    } else {
      try {
        await navigator.clipboard.writeText(url);
      } catch {
        /* noop */
      }
    }
  }

  return (
    <div className="absolute bottom-4 z-20 flex flex-col items-center gap-4 p-2" style={{ insetInlineEnd: 0 }}>
      <Rail label={metrics.likes > 0 ? formatNumber(metrics.likes) : 'إعجاب'} active={liked} onClick={() => react('like')}>
        <svg viewBox="0 0 24 24" fill={liked ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2} className="size-6" aria-hidden>
          <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
      </Rail>
      <Rail label={metrics.favorites > 0 ? formatNumber(metrics.favorites) : 'حفظ'} active={favorited} onClick={toggleFavorite}>
        <svg viewBox="0 0 24 24" fill={favorited ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2} className="size-6" aria-hidden>
          <path strokeLinecap="round" strokeLinejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
        </svg>
      </Rail>
      <Rail label="مشاركة" onClick={share}>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="size-6" aria-hidden>
          <path strokeLinecap="round" strokeLinejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12s-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
        </svg>
      </Rail>
    </div>
  );
}

function NavArrow({ dir, disabled, onClick }: { dir: 'up' | 'down'; disabled: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={dir === 'up' ? 'السابق' : 'التالي'}
      className="flex size-11 items-center justify-center bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20 disabled:opacity-25"
      style={CIRCLE}
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="size-6" aria-hidden>
        <path strokeLinecap="round" strokeLinejoin="round" d={dir === 'up' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'} />
      </svg>
    </button>
  );
}

function Rail({
  children,
  label,
  active = false,
  onClick,
}: {
  children: React.ReactNode;
  label: string;
  active?: boolean;
  onClick: () => void;
}) {
  return (
    <button type="button" onClick={onClick} className="flex flex-col items-center gap-1" aria-label={label}>
      <span
        className={`flex size-11 items-center justify-center backdrop-blur-sm transition ${
          active ? 'bg-primary text-white' : 'bg-black/40 text-white hover:bg-black/55'
        }`}
        style={CIRCLE}
      >
        {children}
      </span>
      <span className="text-xs font-medium text-white drop-shadow">{label}</span>
    </button>
  );
}
