'use client';

import { Star } from 'lucide-react';
import { useFollow, type FollowableType } from '@/lib/use-follow';

// زرّ «تابع» (نمط 365 star) — يعمل فوق `useFollow` (صفر منطق هنا). نجمة ممتلئة + «متابَع» عند المتابعة،
// مفرغة + «تابع» وإلا. الزائر يُحوَّل لـ/login عند النقر (عبر الـHook). `compact` = أيقونة فقط (هيدر ضيّق).
export function FollowButton({
  type,
  id,
  compact = false,
  dark = false,
  bare = false,
  className = '',
}: {
  type: FollowableType;
  id: number;
  compact?: boolean;
  /** سطح داكن (هيدر المباراة/البطولة) — حدود/نصّ فاتحان حين غير متابَع. */
  dark?: boolean;
  /** نجمة فقط بلا حدّ/خلفيّة (رؤوس البطولات وصفوف المباريات) — ممتلئة بالأحمر عند المتابعة. */
  bare?: boolean;
  className?: string;
}) {
  const { following, busy, toggle } = useFollow(type, id);
  const isFollowing = following === true;

  if (bare) {
    return (
      <button
        type="button"
        onClick={toggle}
        disabled={busy}
        aria-pressed={isFollowing}
        aria-label={isFollowing ? 'إلغاء المتابعة' : 'متابعة'}
        className={
          'inline-flex shrink-0 items-center justify-center p-1 transition-colors disabled:opacity-50 ' +
          (isFollowing ? 'text-primary' : 'text-muted hover:text-primary') +
          (className ? ` ${className}` : '')
        }
      >
        <Star className={'size-4 ' + (isFollowing ? 'fill-current' : '')} aria-hidden />
      </button>
    );
  }

  const idle = dark
    ? 'border-white/30 text-white hover:border-white'
    : 'border-border text-fg hover:border-primary hover:text-primary';

  return (
    <button
      type="button"
      onClick={toggle}
      disabled={busy || following === null}
      aria-pressed={isFollowing}
      aria-label={isFollowing ? 'إلغاء المتابعة' : 'متابعة'}
      className={
        'inline-flex shrink-0 items-center gap-1.5 border px-3 py-1.5 text-[13px] font-bold transition-colors disabled:opacity-60 ' +
        (isFollowing ? 'border-primary bg-primary text-white' : idle) +
        (className ? ` ${className}` : '')
      }
    >
      <Star className={'size-4 shrink-0 ' + (isFollowing ? 'fill-current' : '')} aria-hidden />
      {!compact && <span>{isFollowing ? 'متابَع' : 'تابع'}</span>}
    </button>
  );
}
