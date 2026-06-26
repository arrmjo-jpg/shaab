import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  TeamMemberData,
  TeamMembersListParams,
  TeamMembersListResult,
  TeamMemberUpsertPayload,
} from '@/types/team.types';
import type { PaginationMeta } from '@/types/users.types';

function buildParams(p: TeamMembersListParams): Record<string, string | number> {
  const params: Record<string, string | number> = { page: p.page, per_page: p.per_page };
  if (p.search) params['filter[name]'] = p.search;
  if (p.status) params['filter[status]'] = p.status;
  if (p.department) params['filter[department]'] = p.department;
  if (p.sort) params.sort = p.sort;
  if (p.trashed) params.trashed = p.trashed;
  return params;
}

/**
 * فريق العمل — نطاق محتوى تعريفيّ مستقلّ. يعيد استخدام نفس عقد الـ API
 * (ApiResponse + pagination meta) وأنماط بقية الخدمات. لا بنية موازية.
 */
export const teamMembersService = {
  async list(p: TeamMembersListParams): Promise<TeamMembersListResult> {
    const { data } = await http.get<ApiSuccess<TeamMemberData[]>>('/admin/team-members', {
      params: buildParams(p),
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async get(id: number): Promise<TeamMemberData> {
    const { data } = await http.get<ApiSuccess<TeamMemberData>>(`/admin/team-members/${id}`);
    return data.data;
  },

  async create(payload: TeamMemberUpsertPayload): Promise<TeamMemberData> {
    const { data } = await http.post<ApiSuccess<TeamMemberData>>('/admin/team-members', payload);
    return data.data;
  },

  async update(id: number, payload: TeamMemberUpsertPayload): Promise<TeamMemberData> {
    const { data } = await http.put<ApiSuccess<TeamMemberData>>(`/admin/team-members/${id}`, payload);
    return data.data;
  },

  async toggleStatus(id: number, status: TeamMemberData['status']): Promise<string> {
    const { data } = await http.patch<ApiSuccess<TeamMemberData>>(
      `/admin/team-members/${id}/status`,
      { status },
    );
    return data.message;
  },

  async reorder(ids: number[]): Promise<string> {
    const { data } = await http.patch<ApiSuccess<unknown>>('/admin/team-members/reorder', { ids });
    return data.message;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/team-members/${id}`);
    return data.message;
  },

  async restore(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/team-members/${id}/restore`);
    return data.message;
  },

  async forceDelete(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/team-members/${id}/force`);
    return data.message;
  },
};
