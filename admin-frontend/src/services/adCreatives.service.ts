import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { PaginationMeta } from '@/types/users.types';
import type {
  AdCreativeData,
  AdCreativesListParams,
  AdCreativesListResult,
  AdCreativeUpsertPayload,
} from '@/types/advertising.types';

function buildParams(p: AdCreativesListParams): Record<string, string | number> {
  const params: Record<string, string | number> = { page: p.page, per_page: p.per_page };
  if (p.search) params['filter[title]'] = p.search;
  if (p.ad_campaign_id) params['filter[ad_campaign_id]'] = p.ad_campaign_id;
  if (p.type) params['filter[type]'] = p.type;
  if (p.is_active !== '') params['filter[is_active]'] = p.is_active;
  if (p.sort) params.sort = p.sort;
  if (p.trashed) params.trashed = p.trashed;
  return params;
}

/**
 * الإبداعات الإعلانية — image (وسيط مركزيّ) أو html (مُنقّى). مرقَّمة + حذف ناعم/استرجاع/
 * حذف نهائيّ. video مرفوض في الـ backend (ميزة غير مُفعّلة) فلا يُرسَل من الإدارة.
 */
export const adCreativesService = {
  async list(p: AdCreativesListParams): Promise<AdCreativesListResult> {
    const { data } = await http.get<ApiSuccess<AdCreativeData[]>>('/admin/ad-creatives', {
      params: buildParams(p),
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async get(id: number): Promise<AdCreativeData> {
    const { data } = await http.get<ApiSuccess<AdCreativeData>>(`/admin/ad-creatives/${id}`);
    return data.data;
  },

  async create(payload: AdCreativeUpsertPayload): Promise<AdCreativeData> {
    const { data } = await http.post<ApiSuccess<AdCreativeData>>('/admin/ad-creatives', payload);
    return data.data;
  },

  async update(id: number, payload: AdCreativeUpsertPayload): Promise<AdCreativeData> {
    const { data } = await http.put<ApiSuccess<AdCreativeData>>(`/admin/ad-creatives/${id}`, payload);
    return data.data;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/ad-creatives/${id}`);
    return data.message;
  },

  async restore(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/ad-creatives/${id}/restore`);
    return data.message;
  },

  async forceDelete(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/ad-creatives/${id}/force`);
    return data.message;
  },
};
