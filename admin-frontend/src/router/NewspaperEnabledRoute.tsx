import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { PageSkeleton } from '@/components/feedback';
import { useNewspaperSettings } from '@/features/epaper/hooks';
import { paths } from './paths';

/**
 * بوابة وحدة الجريدة الرقمية على مستوى المسار — "معطَّل = غير متاح".
 * أثناء جلب المفتاح تُعرَض هيكلية تحميل (تفادي إعادة توجيه خاطئة)؛ وإن كانت الوحدة
 * معطَّلة يُعاد التوجيه للوحة التحكم (اتّساقاً مع ProtectedRoute للمسارات غير المتاحة).
 * صفحة تفعيل الوحدة في الإعدادات لا تمرّ بهذه البوابة (تبقى متاحة للتفعيل).
 */
export function NewspaperEnabledRoute({ children }: { children: ReactNode }) {
  const q = useNewspaperSettings();

  if (q.isLoading) return <PageSkeleton />;
  if (!q.data?.enabled) return <Navigate to={paths.dashboard} replace />;

  return <>{children}</>;
}
