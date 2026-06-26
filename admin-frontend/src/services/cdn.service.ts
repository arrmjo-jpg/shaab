import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { CdnSettings, CdnStatus, CdnUpdatePayload } from '@/types/cdn.types';

export const cdnService = {
  async status(): Promise<CdnStatus> {
    const { data } = await http.get<ApiSuccess<CdnStatus>>('/admin/cdn/status');
    return data.data;
  },

  // قراءة الإعدادات الكاملة (zone_id/token configured) — endpoint موجود
  async getSettings(): Promise<CdnSettings> {
    const { data } = await http.get<ApiSuccess<CdnSettings>>('/admin/settings/cdn');
    return data.data;
  },

  async update(payload: CdnUpdatePayload): Promise<string> {
    const { data } = await http.put<ApiSuccess<unknown>>('/admin/cdn/settings', payload);
    return data.message;
  },

  async test(): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>('/admin/cdn/test');
    return data.message;
  },

  async purge(urls: string[]): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>('/admin/cdn/purge', { urls });
    return data.message;
  },

  async purgeAll(): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>('/admin/cdn/purge-all');
    return data.message;
  },
};
