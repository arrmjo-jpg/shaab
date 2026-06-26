'use client';

import Link from 'next/link';
import { type ReactNode, useCallback, useEffect, useRef, useState } from 'react';

import { ViewBeacon } from '@/components/engagement/view-beacon';
import { formatNumber, formatRelativeTime } from '@/lib/format';
import type { ReelItem } from '@/lib/reels';
import { useEngagement } from '@/lib/use-engagement';

import { ReelPlayer } from './reel-player';
import { ReelsThemeToggle } from './reels-theme-toggle';

const CIRCLE = { borderRadius: '9999px' } as const;

export interface ReelsNavItem {
  label: string;
  href: string;
  active?: boolean;
}

interface Props {
  initialItems: ReelItem[];
  initialCursor: string | null;
  startId?: number;
  siteName: string;
  logo: string | null;
  navItems: ReelsNavItem[];
  /** كتلتا الحساب/الدخول + السوشيل (مُهيّأتان خادميّاً) — تظهران في درج الجوّال. */
  extrasSlot?: ReactNode;
}

// الـfeed الغامر: تمرير عموديّ snap، تشغيل النشط فقط (IntersectionObserver)،
// تحكّم مشترك بالكتم، تمرير لانهائيّ (cursor عبر BFF)، بدء عند ريل محدّد (deep-link).
export function ReelsFeed({ initialItems, initialCursor, startId, siteName, logo, navItems, extrasSlot }: Props) {
  const [items, setItems] = useState<ReelItem[]>(initialItems);
  const [cursor, setCursor] = useState<string | null>(initialCursor);
  const [activeId, setActiveId] = useState<number | null>(startId ?? initialItems[0]?.id ?? null);
  const [muted, setMuted] = useState(true);
  const [menuOpen, setMenuOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const sentinelRef = useRef<HTMLDivElement>(null);
  const loadingRef = useRef(false);

  // الرابط العميق: مرّر إلى الريل المطلوب عند التحميل.
  useEffect(() => {
    if (!startId) return;
    rootRef.current?.querySelector(`[data-reel="${startId}"]`)?.scrollIntoView({ block: 'start' });
  }, [startId]);

  // تفعيل الريل الظاهر (≥60%).
  useEffect(() => {
    const root = rootRef.current;
    if (!root) return;
    const io = new IntersectionObserver(
      (entries) => {
        for (const e of entries) {
          if (e.isIntersecting && e.intersectionRatio >= 0.6) {
            const id = Number((e.target as HTMLElement).dataset.reel);
            if (id) setActiveId(id);
          }
        }
      },
      { root, threshold: [0, 0.6, 1] },
    );
    root.querySelectorAll('[data-reel]').forEach((el) => io.observe(el));
    return () => io.disconnect();
  }, [items]);

  const loadMore = useCallback(async () => {
    if (loadingRef.current || !cursor) return;
    loadingRef.current = true;
    try {
      const res = await fetch(`/api/reels?cursor=${encodeURIComponent(cursor)}`);
      if (res.ok) {
        const page: { items?: ReelItem[]; nextCursor?: string | null } = await res.json();
        setItems((prev) => [...prev, ...(page.items ?? [])]);
        setCursor(page.nextCursor ?? null);
      }
    } finally {
      loadingRef.current = false;
    }
  }, [cursor]);

  // مراقب التحميل التدريجيّ.
  useEffect(() => {
    const root = rootRef.current;
    const sentinel = sentinelRef.current;
    if (!root || !sentinel || !cursor) return;
    const io = new IntersectionObserver((entries) => entries[0]?.isIntersecting && loadMore(), {
      root,
      threshold: 0.1,
    });
    io.observe(sentinel);
    return () => io.disconnect();
  }, [cursor, loadMore, items.length]);

  if (items.length === 0) return <ReelsEmpty />;

  return (
    <div
      ref={rootRef}
      className="reels-feed h-[100dvh] w-full overflow-y-scroll bg-[var(--rl-bg)]"
      style={{ scrollSnapType: 'y mandatory' }}
    >
      {items.map((reel) => (
        <ReelSlide
          key={reel.id}
          reel={reel}
          active={activeId === reel.id}
          muted={muted}
          onToggleMute={() => setMuted((m) => !m)}
          siteName={siteName}
          logo={logo}
          onOpenMenu={() => setMenuOpen(true)}
        />
      ))}

      {cursor && (
        <div
          ref={sentinelRef}
          className="flex h-24 items-center justify-center text-[var(--rl-muted)]"
          style={{ scrollSnapAlign: 'start' }}
        >
          <span className="size-7 animate-spin border-2 border-[var(--rl-border)] border-t-[var(--rl-fg)]" style={CIRCLE} />
        </div>
      )}

      <ReelsNavDrawer
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
        items={navItems}
        siteName={siteName}
        logo={logo}
        extrasSlot={extrasSlot}
      />
    </div>
  );
}

function ReelSlide({
  reel,
  active,
  muted,
  onToggleMute,
  siteName,
  logo,
  onOpenMenu,
}: {
  reel: ReelItem;
  active: boolean;
  muted: boolean;
  onToggleMute: () => void;
  siteName: string;
  logo: string | null;
  onOpenMenu: () => void;
}) {
  const cardRef = useRef<HTMLDivElement>(null);
  // تفاعل مركزيّ (الريلز Consumer لنظام Engagement العام) — منطق واحد عبر useEngagement؛
  // أسماء مشتقّة (likes/liked/favorites/toggleLike) كي يبقى العرض دون تغيير.
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
  const likes = metrics.likes;
  const liked = reaction === 'like';
  const favorites = metrics.favorites;
  const toggleLike = () => react('like');

  async function share() {
    const url = `${window.location.origin}${reel.href}`;
    if (navigator.share) {
      try {
        await navigator.share({ title: reel.title, url });
      } catch {
        /* ألغى المستخدم */
      }
    } else {
      try {
        await navigator.clipboard.writeText(url);
      } catch {
        /* noop */
      }
    }
  }

  function toggleFullscreen() {
    const el = cardRef.current;
    if (!el) return;
    if (!document.fullscreenElement) el.requestFullscreen?.().catch(() => {});
    else document.exitFullscreen?.().catch(() => {});
  }

  return (
    <section
      data-reel={reel.id}
      className="flex h-[100dvh] w-full items-center justify-center bg-[var(--rl-bg)] p-0 sm:p-4"
      style={{ scrollSnapAlign: 'start', scrollSnapStop: 'always' }}
    >
      {/* منارة المشاهدة — تُحتسب عند تنشيط هذا الريل (active) مدّةَ المكوث فقط (نفس المكوّن الموحَّد). */}
      <ViewBeacon type="reel" id={reel.id} active={active} />
      <div
        ref={cardRef}
        className="relative h-full w-full overflow-hidden bg-black sm:aspect-[9/16] sm:h-[calc(100dvh-2rem)] sm:w-auto"
        style={{ borderRadius: '0' }}
      >
        <div className="reels-card-round absolute inset-0 overflow-hidden" style={{ borderRadius: 'inherit' }}>
          <ReelPlayer hls={reel.hls} mp4={reel.mp4} poster={reel.poster} active={active} muted={muted} />
        </div>

        {/* تدرّجات لقراءة العناصر فوق الفيديو */}
        <div
          className="pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-b from-black/70 to-transparent"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-black/85 to-transparent"
          aria-hidden
        />

        {/* الشريط العلويّ: الشعار يميناً، والتحكّم يساراً (لا يتعارضان) */}
        <div className="absolute inset-x-0 top-0 z-20 flex items-center justify-between gap-2 p-3">
          {logo ? (
            <span className="pointer-events-none">
              {/* eslint-disable-next-line @next/next/no-img-element -- شعار العلامة */}
              <img src={logo} alt="" className="h-7 w-auto object-contain opacity-90 drop-shadow-md" />
            </span>
          ) : (
            <span />
          )}
          <div className="flex items-center gap-2">
            <Link
              href="/"
              aria-label="العودة للموقع"
              className="flex size-9 items-center justify-center bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20"
              style={CIRCLE}
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="size-5" aria-hidden>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </Link>
            <button
              type="button"
              onClick={onToggleMute}
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
            <button
              type="button"
              onClick={toggleFullscreen}
              aria-label="ملء الشاشة"
              className="flex size-9 items-center justify-center bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20"
              style={CIRCLE}
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} className="size-5" aria-hidden>
                <path strokeLinecap="round" strokeLinejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
              </svg>
            </button>
          </div>
        </div>

        {/* الميتا (بداية/يمين): القناة + العنوان + الوصف + التاريخ */}
        <div className="absolute bottom-0 z-20 max-w-[78%] p-4 pe-2 text-white" style={{ insetInlineStart: 0 }}>
          <div className="mb-2 flex items-center gap-2">
            <span
              className="flex size-7 items-center justify-center bg-primary text-xs font-black text-primary-foreground"
              style={{ borderRadius: '8px' }}
              aria-hidden
            >
              {logo ? (
                // eslint-disable-next-line @next/next/no-img-element -- علامة قناة صغيرة
                <img src={logo} alt="" className="size-full object-contain p-0.5" />
              ) : (
                siteName.slice(0, 1)
              )}
            </span>
            <span className="text-sm font-bold drop-shadow">{siteName}</span>
            <Link
              href="/"
              className="border border-white/40 px-2.5 py-0.5 text-[11px] font-medium text-white transition hover:bg-white/10"
              style={CIRCLE}
            >
              تابعنا
            </Link>
          </div>
          <h1 className="mb-1 line-clamp-2 text-base font-bold leading-snug drop-shadow">{reel.title}</h1>
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

        {/* شريط التفاعل (نهاية/يسار): القائمة (جوّال) + إعجاب + مفضّلة + مشاركة */}
        <div className="absolute bottom-4 z-20 flex flex-col items-center gap-5 p-2" style={{ insetInlineEnd: 0 }}>
          {/* أيقونة القائمة (الشريط الجانبيّ) فوق الإعجابات — جوّال فقط (الديسك توب يُظهر الشريط الجانبيّ) */}
          <RailButton label="القائمة" onClick={onOpenMenu} className="sm:hidden">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="size-6" aria-hidden>
              <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
            </svg>
          </RailButton>
          <RailButton label={likes > 0 ? formatNumber(likes) : 'إعجاب'} active={liked} onClick={toggleLike}>
            <svg viewBox="0 0 24 24" fill={liked ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2} className="size-6" aria-hidden>
              <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
          </RailButton>
          <RailButton label={favorites > 0 ? formatNumber(favorites) : 'حفظ'} active={favorited} onClick={toggleFavorite}>
            <svg viewBox="0 0 24 24" fill={favorited ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2} className="size-6" aria-hidden>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
            </svg>
          </RailButton>
          <RailButton label="مشاركة" onClick={share}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="size-6" aria-hidden>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12s-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
            </svg>
          </RailButton>
        </div>
      </div>
    </section>
  );
}

function RailButton({
  children,
  label,
  active = false,
  onClick,
  className,
}: {
  children: React.ReactNode;
  label: string;
  active?: boolean;
  onClick: () => void;
  className?: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`flex flex-col items-center gap-1 ${className ?? ''}`}
      aria-label={label}
    >
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

// درج التنقّل على الجوّال (يفتحه زرّ «القائمة» في الشريط) — نظير الشريط الجانبيّ للديسك توب، بدعم RTL.
function ReelsNavDrawer({
  open,
  onClose,
  items,
  siteName,
  logo,
  extrasSlot,
}: {
  open: boolean;
  onClose: () => void;
  items: ReelsNavItem[];
  siteName: string;
  logo: string | null;
  extrasSlot?: ReactNode;
}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[80] sm:hidden" role="dialog" aria-modal="true">
      {/* الخلفية المعتمة */}
      <button
        type="button"
        aria-label="إغلاق القائمة"
        onClick={onClose}
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
      />
      {/* اللوحة — من جهة البداية (يمين في العربيّة) */}
      <aside
        className="absolute inset-y-0 flex w-[82%] max-w-[300px] flex-col bg-[var(--rl-panel)] text-[var(--rl-fg)] shadow-2xl"
        style={{ insetInlineStart: 0 }}
      >
        <div className="flex items-center justify-between border-b border-[var(--rl-border)] p-4">
          <Link href="/" aria-label={siteName} className="flex items-center" onClick={onClose}>
            {logo ? (
              // eslint-disable-next-line @next/next/no-img-element -- شعار العلامة
              <img src={logo} alt={siteName} className="h-8 w-auto object-contain" />
            ) : (
              <span className="text-base font-black">{siteName}</span>
            )}
          </Link>
          <button
            type="button"
            onClick={onClose}
            aria-label="إغلاق"
            className="flex size-9 items-center justify-center bg-[var(--rl-hover)] text-[var(--rl-fg)] transition hover:opacity-90"
            style={CIRCLE}
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="size-5" aria-hidden>
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
          {items.map((it) => (
            <Link
              key={it.href}
              href={it.href}
              onClick={onClose}
              className={`px-4 py-3 text-sm font-bold transition-colors ${
                it.active ? 'bg-[var(--rl-hover)] text-primary' : 'text-[var(--rl-fg)] hover:bg-[var(--rl-hover)]'
              }`}
              style={{ borderRadius: '10px' }}
            >
              {it.label}
            </Link>
          ))}
        </nav>

        {/* الأسفل: الحساب/الدخول + السوشيل + الثيم (نظير الشريط الجانبيّ) */}
        <div className="space-y-3 border-t border-[var(--rl-border)] p-3">
          {extrasSlot}
          <ReelsThemeToggle className="w-full justify-start" />
        </div>
      </aside>
    </div>
  );
}

function ReelsEmpty() {
  return (
    <div className="flex h-[100dvh] w-full flex-col items-center justify-center gap-3 bg-black text-center text-white">
      <h1 className="font-heading text-h3 font-bold">لا توجد ريلز بعد</h1>
      <p className="max-w-xs text-sm text-white/60">ستظهر هنا أحدث الريلز فور نشرها.</p>
      <Link href="/" className="mt-2 bg-primary px-5 py-2 text-sm font-bold text-white" style={CIRCLE}>
        العودة للرئيسية
      </Link>
    </div>
  );
}
