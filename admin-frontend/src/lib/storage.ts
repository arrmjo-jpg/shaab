import { env } from './env';

/**
 * يبني رابط ملف عام مخزّن على قرص Laravel public.
 * الافتراض: تم تشغيل `php artisan storage:link`.
 * يشتق الأصل من VITE_API_BASE_URL (بحذف /api/...).
 */
export function storageUrl(path: string | null | undefined): string | null {
  if (!path) return null;
  try {
    const origin = new URL(env.apiBaseUrl).origin;
    return `${origin}/storage/${path.replace(/^\/+/, '')}`;
  } catch {
    return null;
  }
}
