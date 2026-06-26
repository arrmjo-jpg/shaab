'use client';

import Link from 'next/link';
import { useTransition } from 'react';

import { BellIcon, DashboardIcon, FileTextIcon, LogOutIcon, UserIcon } from '@/components/icons';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { logoutAction } from '@/lib/account-actions';

// Authenticated header menu: avatar (with unread badge) → dropdown. Logout runs the server action.
export function UserMenu({
  name,
  avatar,
  isWriter,
  unread,
}: {
  name: string;
  avatar?: string | null;
  isWriter: boolean;
  unread: number;
}) {
  const [pending, startTransition] = useTransition();
  const initial = name?.trim().charAt(0) || '؟';
  const badge = unread > 9 ? '9+' : String(unread);

  return (
    <DropdownMenu dir="rtl">
      <DropdownMenuTrigger
        aria-label="حسابي"
        className="avatar relative inline-flex size-9 items-center justify-center rounded-full text-fg outline-none ring-primary/40 focus-visible:ring-2 data-[state=open]:ring-2"
      >
        {/* Inner clips the image/initial to the circle; the badge lives OUTSIDE it so it floats. */}
        <span className="avatar flex size-full items-center justify-center overflow-hidden rounded-full bg-surface-2">
          {avatar ? (
            // eslint-disable-next-line @next/next/no-img-element -- raw <img> until the unified Image-Platform slice
            <img src={avatar} alt={name} className="size-full object-cover" />
          ) : (
            <span className="font-heading text-sm font-bold">{initial}</span>
          )}
        </span>
        {unread > 0 && (
          <span className="avatar absolute -end-1 -top-1 z-10 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold leading-4 text-primary-foreground ring-2 ring-surface">
            {badge}
          </span>
        )}
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-56">
        <p className="truncate px-2 py-1.5 text-sm font-bold text-fg">{name}</p>

        <DropdownMenuItem asChild>
          <Link href="/account" className="flex cursor-pointer items-center gap-2">
            <DashboardIcon className="size-4" aria-hidden />
            لوحة التحكم
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href="/account/profile" className="flex cursor-pointer items-center gap-2">
            <UserIcon className="size-4" aria-hidden />
            ملفي الشخصي
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href="/account/notifications" className="flex cursor-pointer items-center justify-between gap-2">
            <span className="flex items-center gap-2">
              <BellIcon className="size-4" aria-hidden />
              الإشعارات
            </span>
            {unread > 0 && (
              <span className="avatar rounded-full bg-primary px-1.5 text-[10px] font-bold text-primary-foreground">{badge}</span>
            )}
          </Link>
        </DropdownMenuItem>
        {isWriter && (
          <DropdownMenuItem asChild>
            <Link href="/account/content?tab=articles" className="flex cursor-pointer items-center gap-2">
              <FileTextIcon className="size-4" aria-hidden />
              مقالاتي
            </Link>
          </DropdownMenuItem>
        )}

        <div className="my-1 h-px bg-border" />

        <DropdownMenuItem
          onSelect={(e) => {
            e.preventDefault();
            startTransition(() => logoutAction());
          }}
          className="flex cursor-pointer items-center gap-2 text-danger focus:text-danger"
        >
          <LogOutIcon className="size-4" aria-hidden />
          {pending ? 'جارٍ الخروج…' : 'تسجيل الخروج'}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
