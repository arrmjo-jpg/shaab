import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { AdZoneData, AdZoneUpsertPayload } from '@/types/advertising.types';

/**
 * المساحات الإعلانية — كيان إعداد منخفض العدد (قائمة غير مرقَّمة). يعيد استخدام عقد
 * الـ API نفسه. الحذف صلب (محميّ بحارس الإسنادات في الـ backend).
 */
export const adZonesService = {
  async list(): Promise<AdZoneData[]> {
    const { data } = await http.get<ApiSuccess<AdZoneData[]>>('/admin/ad-zones');
    return data.data;
  },

  async get(id: number): Promise<AdZoneData> {
    const { data } = await http.get<ApiSuccess<AdZoneData>>(`/admin/ad-zones/${id}`);
    return data.data;
  },

  async create(payload: AdZoneUpsertPayload): Promise<AdZoneData> {
    const { data } = await http.post<ApiSuccess<AdZoneData>>('/admin/ad-zones', payload);
    return data.data;
  },

  async update(id: number, payload: AdZoneUpsertPayload): Promise<AdZoneData> {
    const { data } = await http.put<ApiSuccess<AdZoneData>>(`/admin/ad-zones/${id}`, payload);
    return data.data;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/ad-zones/${id}`);
    return data.message;
  },
};
