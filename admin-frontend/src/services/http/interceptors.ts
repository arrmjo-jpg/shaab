import type { AxiosInstance } from 'axios';
import type { NormalizedError } from '@/types/api';

export function attachInterceptors(instance: AxiosInstance): void {
  // إرفاق الـ Bearer token
  instance.interceptors.request.use((config) => {
    const token = localStorage.getItem('alphacms.admin.token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  });

  // توحيد الأخطاء + خروج قسري عند 401
  instance.interceptors.response.use(
    (response) => response,
    async (error) => {
      const status: number = error?.response?.status ?? 0;
      const body = error?.response?.data ?? {};

      if (status === 401) {
        // import ديناميكي لتفادي الدورة
        const { setStoredToken, triggerForcedLogout } = await import('./client');
        setStoredToken(null);
        triggerForcedLogout();
      }

      const normalized: NormalizedError = {
        status,
        message: typeof body?.message === 'string' && body.message !== '' ? body.message : 'حدث خطأ غير متوقع.',
        errors: body?.errors ?? {},
      };

      return Promise.reject(normalized);
    },
  );
}
