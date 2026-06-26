'use client';

import { Suspense, useState } from 'react';

import { MenuIcon } from '@/components/icons';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

import { DashboardNavLinks } from './dashboard-nav-links';
import { LogoutButton } from './logout-button';
import { UserSummary } from './user-summary';

// Mobile drawer navigation (app-like). Opens the role-based nav in a Sheet.
export function DashboardMobileNav({
  isWriter,
  name,
  avatar,
  role,
}: {
  isWriter: boolean;
  name: string;
  avatar?: string | null;
  role: string;
}) {
  const [open, setOpen] = useState(false);

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>
        <Button variant="ghost" size="icon" aria-label="القائمة">
          <MenuIcon className="size-6" aria-hidden />
        </Button>
      </SheetTrigger>
      <SheetContent side="start" className="flex w-72 flex-col gap-0 p-0">
        <SheetTitle className="sr-only">لوحة التحكم</SheetTitle>
        <div className="border-b border-border p-5">
          <UserSummary name={name} avatar={avatar} role={role} />
        </div>
        <Suspense fallback={null}>
          <DashboardNavLinks isWriter={isWriter} onNavigate={() => setOpen(false)} />
        </Suspense>
        <div className="border-t border-border p-3">
          <LogoutButton />
        </div>
      </SheetContent>
    </Sheet>
  );
}
