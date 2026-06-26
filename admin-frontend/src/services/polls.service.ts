import { http } from './http/client';
import type { AnalyticsRangeKey } from '@/types/analytics.types';
import type { ApiSuccess } from '@/types/api';
import type { PaginationMeta } from '@/types/users.types';
import type {
  PollData,
  PollEntityAnalytics,
  PollFleetAnalytics,
  PollsListParams,
  PollsListResult,
  PollUpsertPayload,
} from '@/types/polls.types';

function buildParams(p: PollsListParams): Record<string, string | number> {
  const params: Record<string, string | number> = { page: p.page, per_page: p.per_page };
  if (p.search) params['filter[question]'] = p.search;
  if (p.is_active) params['filter[is_active]'] = p.is_active;
  if (p.sort) params.sort = p.sort;
  if (p.trashed) params.trashed = p.trashed;
  return params;
}

/**
 * الاستطلاعات — مرقَّمة. الإنشاء/التحديث لا يقبلان is_active؛ التفعيل إجراء نشر مستقلّ
 * (PATCH /active مع صلاحية polls.publish). حذف ناعم + استرجاع + حذف نهائيّ.
 */
export const pollsService = {
  async list(p: PollsListParams): Promise<PollsListResult> {
    const { data } = await http.get<ApiSuccess<PollData[]>>('/admin/polls', {
      params: buildParams(p),
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async get(id: number): Promise<PollData> {
    const { data } = await http.get<ApiSuccess<PollData>>(`/admin/polls/${id}`);
    return data.data;
  },

  /** تحليلات أسطول الاستطلاعات (مؤشّرات + توزيع الحالات + متصدّرون + مشاركة حديثة). */
  async analytics(): Promise<PollFleetAnalytics> {
    const { data } = await http.get<ApiSuccess<PollFleetAnalytics>>('/admin/polls/analytics');
    return data.data;
  },

  /** تحليلات استطلاع واحد (سياقيّة) — نطاق زمني عبر range/from/to. */
  async entityAnalytics(
    id: number,
    range: AnalyticsRangeKey,
    from?: string,
    to?: string,
  ): Promise<PollEntityAnalytics> {
    const params: Record<string, string> = { range };
    if (range === 'custom' && from) params.from = from;
    if (range === 'custom' && to) params.to = to;
    const { data } = await http.get<ApiSuccess<PollEntityAnalytics>>(`/admin/polls/${id}/analytics`, {
      params,
    });
    return data.data;
  },

  async create(payload: PollUpsertPayload): Promise<PollData> {
    const { data } = await http.post<ApiSuccess<PollData>>('/admin/polls', payload);
    return data.data;
  },

  async update(id: number, payload: PollUpsertPayload): Promise<PollData> {
    const { data } = await http.put<ApiSuccess<PollData>>(`/admin/polls/${id}`, payload);
    return data.data;
  },

  /** تبديل التفعيل (إجراء نشر مستقلّ) — يتطلّب صلاحية polls.publish. */
  async toggleActive(id: number): Promise<PollData> {
    const { data } = await http.patch<ApiSuccess<PollData>>(`/admin/polls/${id}/active`);
    return data.data;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/polls/${id}`);
    return data.message;
  },

  async restore(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/polls/${id}/restore`);
    return data.message;
  },

  async forceDelete(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/polls/${id}/force`);
    return data.message;
  },
};
