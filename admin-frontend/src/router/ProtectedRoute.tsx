import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { paths } from './paths';

/**
 * بوابة صلاحية على مستوى المسار (المصادقة يتكفّل بها AdminLayout).
 * - permission: صلاحية واحدة مطلوبة.
 * - anyPermission: يكفي امتلاك واحدة منها (OR) — يطابق منطق الباك إند `a|b`.
 */
export function ProtectedRoute({
  permission,
  anyPermission,
  children,
}: {
  permission?: string;
  anyPermission?: string[];
  children: ReactNode;
}) {
  const { hasPermission } = useAuth();

  const denied =
    (permission !== undefined && !hasPermission(permission)) ||
    (anyPermission !== undefined && !anyPermission.some((p) => hasPermission(p)));

  if (denied) {
    return <Navigate to={paths.dashboard} replace />;
  }

  return <>{children}</>;
}
