import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { VideoCategoryData, VideoCategoryUpsertPayload } from '@/types/videoLibrary.types';

/**
 * تصنيفات مكتبة الفيديو — شجرة هرمية مستقلّة (VideoCategoryController). نفس عقد
 * تصنيفات الأخبار بنيوياً (شجرة + move + restore/force) لكن لنطاق الفيديو.
 */
export const videoCategoriesService = {
  async tree(): Promise<VideoCategoryData[]> {
    const { data } = await http.get<ApiSuccess<VideoCategoryData[]>>('/admin/video-categories');
    return data.data;
  },

  async get(id: number): Promise<VideoCategoryData> {
    const { data } = await http.get<ApiSuccess<VideoCategoryData>>(`/admin/video-categories/${id}`);
    return data.data;
  },

  async create(payload: VideoCategoryUpsertPayload): Promise<VideoCategoryData> {
    const { data } = await http.post<ApiSuccess<VideoCategoryData>>('/admin/video-categories', payload);
    return data.data;
  },

  async update(id: number, payload: VideoCategoryUpsertPayload): Promise<VideoCategoryData> {
    const { data } = await http.put<ApiSuccess<VideoCategoryData>>(`/admin/video-categories/${id}`, payload);
    return data.data;
  },

  /** نقل ضمن الإخوة (تبديل صاعد/هابط) — مرآة تصنيفات الأخبار (direction، لا sort_order). */
  async move(id: number, direction: 'up' | 'down'): Promise<string> {
    const { data } = await http.patch<ApiSuccess<unknown>>(`/admin/video-categories/${id}/move`, { direction });
    return data.message;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/video-categories/${id}`);
    return data.message;
  },

  async restore(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/video-categories/${id}/restore`);
    return data.message;
  },

  async forceDelete(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/video-categories/${id}/force`);
    return data.message;
  },
};
