import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { paths } from '@/router/paths';
import { LoadingState } from '@/components/feedback';
import { AuthCover } from './auth/AuthCover';

/** تخطيط شاشة منقسمة للمصادقة — نموذج + غلاف ديناميكي */
export function AuthLayout() {
  const { status } = useAuth();

  if (status === 'idle' || status === 'loading') {
    return (
      <div className="grid min-h-screen place-items-center">
        <LoadingState />
      </div>
    );
  }

  if (status === 'authenticated') {
    return <Navigate to={paths.dashboard} replace />;
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <AuthCover />
      <div className="flex items-center justify-center p-6 sm:p-10">
        <div className="w-full max-w-sm animate-fade-in">
          <Outlet />
        </div>
      </div>
    </div>
  );
}
