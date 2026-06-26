'use client';

import { formatNumber } from '@/lib/format';
import { useEngagement, type EngageableType, type EngagementMetrics } from '@/lib/use-engagement';

// شريط تفاعل أفقيّ **مشترك** (مقال/فيديو) — عرض فقط فوق الـHook المركزيّ `useEngagement`
// (صفر منطق إعجاب/حفظ هنا). نمط الإعجاب: `thumbs` (إعجاب + عدم إعجاب، للمقال) أو `heart`
// (إعجاب واحد، للفيديو/الوسائط). مشاركة بلا تسجيل؛ الإعجاب/الحفظ يتبعان سياسة الـBFF
// (الزائر على فعل يتطلّب دخولاً ⇒ 401 ⇒ الـHook يحوّله لـ/login?returnTo).
export function EngagementBar({
  type,
  id,
  href,
  title,
  initialMetrics,
  reactionStyle = 'thumbs',
  hydrate = false,
  showShare = true,
  className = '',
}: {
  type: EngageableType;
  id: number;
  href: string;
  title: string;
  initialMetrics: EngagementMetrics;
  reactionStyle?: 'thumbs' | 'heart';
  hydrate?: boolean;
  /** زرّ المشاركة الأصليّ — يُخفى حين يكون شريط مشاركة مستقلّ بجواره (تفادي الازدواج). */
  showShare?: boolean;
  className?: string;
}) {
  const { metrics, reaction, favorited, react, toggleFavorite } = useEngagement({
    type,
    id,
    initialMetrics,
    hydrate,
  });

  async function share() {
    const url = `${window.location.origin}${href}`;
    if (navigator.share) {
      try {
        await navigator.share({ title, url });
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

  return (
    <div className={`relative z-20 flex items-center justify-between ${className}`}>
      {/* إعجاب (/ عدم إعجاب) — بداية */}
      <div className="flex items-center gap-1">
        {reactionStyle === 'heart' ? (
          <CountButton
            label="إعجاب"
            count={metrics.likes}
            active={reaction === 'like'}
            onClick={() => react('like')}
          >
            <HeartIcon filled={reaction === 'like'} />
          </CountButton>
        ) : (
          <>
            <CountButton
              label="إعجاب"
              count={metrics.likes}
              active={reaction === 'like'}
              onClick={() => react('like')}
            >
              <ThumbUpIcon />
            </CountButton>
            <CountButton
              label="عدم الإعجاب"
              count={metrics.dislikes}
              active={reaction === 'dislike'}
              onClick={() => react('dislike')}
            >
              <ThumbDownIcon />
            </CountButton>
          </>
        )}
      </div>

      {/* مشاركة + حفظ — نهاية */}
      <div className="flex items-center gap-0.5">
        {showShare ? (
          <IconButton label="مشاركة" onClick={share}>
            <ShareIcon />
          </IconButton>
        ) : null}
        <IconButton label="حفظ في المفضّلة" active={favorited} onClick={toggleFavorite}>
          <BookmarkIcon filled={favorited} />
        </IconButton>
      </div>
    </div>
  );
}

function CountButton({
  children,
  label,
  count,
  active,
  onClick,
}: {
  children: React.ReactNode;
  label: string;
  count: number;
  active: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      aria-pressed={active}
      className={`flex items-center gap-1 px-2 py-1 text-caption font-bold transition-colors ${
        active ? 'text-primary' : 'text-muted hover:text-primary'
      }`}
    >
      <span className="size-[18px]">{children}</span>
      <span className="tabular-nums">{formatNumber(count)}</span>
    </button>
  );
}

function IconButton({
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
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      aria-pressed={active}
      className={`flex size-8 items-center justify-center transition-colors ${
        active ? 'text-primary' : 'text-muted hover:text-primary'
      }`}
    >
      <span className="size-[18px]">{children}</span>
    </button>
  );
}

function HeartIcon({ filled }: { filled: boolean }) {
  return (
    <svg viewBox="0 0 24 24" fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2} className="size-full" aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 21s-7.5-4.7-10-9.2C.6 9 1.5 5.5 4.6 4.6 6.7 4 8.8 4.9 12 8c3.2-3.1 5.3-4 7.4-3.4 3.1.9 4 4.4 2.6 7.2C19.5 16.3 12 21 12 21z" />
    </svg>
  );
}

function ThumbUpIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className="size-full" aria-hidden>
      <path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z" />
    </svg>
  );
}

function ThumbDownIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className="size-full" aria-hidden>
      <path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z" />
    </svg>
  );
}

function ShareIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="size-full" aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v12m0-12L8 8m4-4 4 4M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" />
    </svg>
  );
}

function BookmarkIcon({ filled }: { filled: boolean }) {
  return (
    <svg viewBox="0 0 24 24" fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={2} className="size-full" aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M5 5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16l-7-3.5L5 21V5z" />
    </svg>
  );
}
