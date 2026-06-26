import { http } from './http/client';
import type { AnalyticsRangeKey } from '@/types/analytics.types';
import type { ApiSuccess } from '@/types/api';
import type { AdAnalyticsData } from '@/types/advertising.types';

/**
 * تحليلات الإعلانات التجميعيّة — نطاق زمنيّ عبر range/from/to (مرآة اتفاقية التحليلات).
 */
export const adAnalyticsService = {
  async get(range: AnalyticsRangeKey, from?: string, to?: string): Promise<AdAnalyticsData> {
    const params: Record<string, string> = { range };
    if (range === 'custom' && from) params.from = from;
    if (range === 'custom' && to) params.to = to;

    const { data } = await http.get<ApiSuccess<AdAnalyticsData>>('/admin/ads/analytics', { params });
    return data.data;
  },
};
