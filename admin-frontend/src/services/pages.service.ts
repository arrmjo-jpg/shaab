import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  PageData,
  PagesListParams,
  PagesListResult,
  PageUpsertPayload,
} from '@/types/content.types';
import type { PaginationMeta } from '@/types/users.types';

function buildParams(p: PagesListParams): Record<string, string | number> {
  const params: Record<string, string | number> = { page: p.page, per_page: p.per_page };
  if (p.search) params['filter[title]'] = p.search;
  if (p.status) params['filter[status]'] = p.status;
  if (p.locale) params['filter[locale]'] = p.locale;
  if (p.show_in_header) params['filter[show_in_header]'] = p.show_in_header;
  if (p.show_in_footer) params['filter[show_in_footer]'] = p.show_in_footer;
  if (p.sort) params.sort = p.sort;
  if (p.trashed) params.trashed = p.trashed;
  return params;
}

/**
 * الصفحات الثابتة المُدارة عبر CMS — نفس عقد الـ API (ApiResponse + pagination
 * meta) المستخدم في بقية أنواع المحتوى. لا /stats ولا /analytics للصفحات.
 */
export const pagesService = {
  async list(p: PagesListParams): Promise<PagesListResult> {
    const { data } = await http.get<ApiSuccess<PageData[]>>('/admin/pages', {
      params: buildParams(p),
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async get(id: number): Promise<PageData> {
    const { data } = await http.get<ApiSuccess<PageData>>(`/admin/pages/${id}`);
    return data.data;
  },

  async create(payload: PageUpsertPayload): Promise<PageData> {
    const { data } = await http.post<ApiSuccess<PageData>>('/admin/pages', payload);
    return data.data;
  },

  async update(id: number, payload: PageUpsertPayload): Promise<PageData> {
    const { data } = await http.put<ApiSuccess<PageData>>(`/admin/pages/${id}`, payload);
    return data.data;
  },

  async transition(id: number, status: string, publishedAt?: string | null): Promise<string> {
    const { data } = await http.patch<ApiSuccess<PageData>>(`/admin/pages/${id}/status`, {
      status,
      published_at: publishedAt ?? undefined,
    });
    return data.message;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/pages/${id}`);
    return data.message;
  },

  async restore(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/pages/${id}/restore`);
    return data.message;
  },

  async forceDelete(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/pages/${id}/force`);
    return data.message;
  },
};
