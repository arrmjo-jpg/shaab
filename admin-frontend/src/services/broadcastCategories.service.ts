import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { BroadcastCategoryData, BroadcastCategoryUpsertPayload } from '@/types/broadcast.types';

/**
 * تصنيفات البثّ — قائمة مسطّحة مستقلّة (لا تشجير، لا locale، لا parent).
 * slug فريد عام. نفس عقد بقية الخدمات بنيوياً لكن لنطاق البثّ.
 */
export const broadcastCategoriesService = {
  async list(): Promise<BroadcastCategoryData[]> {
    const { data } = await http.get<ApiSuccess<BroadcastCategoryData[]>>('/admin/broadcast-categories');
    return data.data;
  },

  async get(id: number): Promise<BroadcastCategoryData> {
    const { data } = await http.get<ApiSuccess<BroadcastCategoryData>>(`/admin/broadcast-categories/${id}`);
    return data.data;
  },

  async create(payload: BroadcastCategoryUpsertPayload): Promise<BroadcastCategoryData> {
    const { data } = await http.post<ApiSuccess<BroadcastCategoryData>>('/admin/broadcast-categories', payload);
    return data.data;
  },

  async update(id: number, payload: BroadcastCategoryUpsertPayload): Promise<BroadcastCategoryData> {
    const { data } = await http.put<ApiSuccess<BroadcastCategoryData>>(
      `/admin/broadcast-categories/${id}`,
      payload,
    );
    return data.data;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/broadcast-categories/${id}`);
    return data.message;
  },
};
