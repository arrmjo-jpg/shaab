import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { SiteAnalytics } from '@/types/analytics.types';

/** لوحة تحليلات الموقع الموحّدة — تستهلك endpoint التجميع القائم (قراءة-فقط). */
export const siteAnalyticsService = {
  async get(): Promise<SiteAnalytics> {
    const { data } = await http.get<ApiSuccess<SiteAnalytics>>('/admin/dashboard');
    return data.data;
  },
};
