import { Suspense } from 'react';

import { DashboardMobileNav } from '@/components/account/dashboard-mobile-nav';
import { DashboardNavLinks } from '@/components/account/dashboard-nav-links';
import { LogoutButton } from '@/components/account/logout-button';
import { UserSummary, roleLabel } from '@/components/account/user-summary';
import { Container } from '@/components/layout/container';
import { requireUser } from '@/lib/auth';

// Dashboard lives INSIDE the site chrome (header + breaking bar + footer come from (site)/layout).
// Here we only lay out the sidebar (≤280px) + main content. Mobile: drawer trigger at the top.
export default async function AccountLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const user = await requireUser();
  const role = roleLabel(user);
  const avatar = user.avatar ?? null;

  return (
    <Container className="py-6 lg:py-8">
      {/* Mobile drawer trigger */}
      <div className="mb-4 flex items-center gap-3 lg:hidden">
        <DashboardMobileNav isWriter={user.is_writer} name={user.name} avatar={avatar} role={role} />
        <span className="font-heading text-base font-bold text-fg">لوحتي</span>
      </div>

      <div className="flex gap-6 lg:gap-8">
        {/* Desktop sidebar */}
        <aside className="sticky top-20 hidden h-fit w-[280px] shrink-0 lg:block">
          <div className="overflow-hidden rounded-xl border border-border bg-surface">
            <div className="border-b border-border p-4">
              <UserSummary name={user.name} avatar={avatar} role={role} />
            </div>
            <Suspense fallback={null}>
              <DashboardNavLinks isWriter={user.is_writer} />
            </Suspense>
            <div className="border-t border-border p-3">
              <LogoutButton />
            </div>
          </div>
        </aside>

        {/* Main content */}
        <div className="min-w-0 flex-1">{children}</div>
      </div>
    </Container>
  );
}
