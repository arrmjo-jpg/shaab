'use client';

import { logoutAction } from '@/lib/account-actions';
import { LogOutIcon } from '@/components/icons';

export function LogoutButton() {
  return (
    <form action={logoutAction}>
      <button
        type="submit"
        className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-danger transition-colors hover:bg-danger/10"
      >
        <LogOutIcon className="size-5 shrink-0" aria-hidden />
        تسجيل الخروج
      </button>
    </form>
  );
}
