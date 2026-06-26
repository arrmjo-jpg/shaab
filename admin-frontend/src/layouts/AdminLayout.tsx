import { useState } from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { paths } from '@/router/paths';
import { LoadingState } from '@/components/feedback';
import { cn } from '@/lib/utils';
import { STORAGE_KEYS } from '@/lib/constants';
import { Sidebar } from './components/Sidebar';
import { Topbar } from './components/Topbar';

const COLLAPSE_KEY = `${STORAGE_KEYS.theme}.sidebar.collapsed`;

export function AdminLayout() {
  const { status, user } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(
    () => localStorage.getItem(COLLAPSE_KEY) === '1',
  );

  const toggleCollapsed = () => {
    setCollapsed((c) => {
      const next = !c;
      localStorage.setItem(COLLAPSE_KEY, next ? '1' : '0');
      return next;
    });
  };

  if (status === 'idle' || status === 'loading') {
    return (
      <div className="grid min-h-screen place-items-center">
        <LoadingState />
      </div>
    );
  }

  if (status === 'unauthenticated') {
    return <Navigate to={paths.login} replace />;
  }

  // بريد غير مؤكَّد → يُطلَع المستخدم لصفحة التأكيد ولو كان مصادَقاً
  if (user && !user.email_verified) {
    return (
      <Navigate to={paths.verifyEmail} replace state={{ email: user.email }} />
    );
  }

  const sidebarW = collapsed ? 'lg:w-16' : 'lg:w-64';
  const contentPs = collapsed ? 'lg:ps-16' : 'lg:ps-64';

  return (
    <div className="min-h-screen bg-secondary/30">
      {/* قائمة جانبية ثابتة ملاصقة للحافة — بلا هوامش، حدّ داخلي فقط */}
      <div
        className={cn(
          'fixed inset-y-0 start-0 z-40 hidden border-e border-border bg-background transition-[width] duration-200 lg:block',
          sidebarW,
        )}
      >
        <Sidebar collapsed={collapsed} onToggle={toggleCollapsed} />
      </div>

      {/* درج الجوال — ملاصق للحافة بلا هوامش */}
      {mobileOpen ? (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div
            className="absolute inset-0 bg-foreground/40 backdrop-blur-sm"
            onClick={() => setMobileOpen(false)}
            aria-hidden
          />
          <div className="absolute inset-y-0 start-0 w-64 animate-fade-in border-e border-border bg-background shadow-soft-lg">
            <Sidebar onNavigate={() => setMobileOpen(false)} />
          </div>
        </div>
      ) : null}

      {/* العمود الرئيسي — يبدأ تماماً عند حافة الـ sidebar (navbar ملاصق) */}
      <div className={cn('flex min-h-screen flex-col transition-[padding] duration-200', contentPs)}>
        <Topbar onOpenSidebar={() => setMobileOpen(true)} />
        <main className="flex-1 p-4 sm:p-6 lg:p-8">
          <div className="mx-auto max-w-screen-2xl animate-fade-in">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}
