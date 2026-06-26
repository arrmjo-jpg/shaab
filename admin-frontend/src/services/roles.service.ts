import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { RoleData, RoleUpsertPayload } from '@/types/rbac.types';
import type { PaginationMeta } from '@/types/users.types';

export interface RolesListResult {
  data: RoleData[];
  pagination: PaginationMeta;
}

export const rolesService = {
  async list(params: { page: number; per_page: number; search: string }): Promise<RolesListResult> {
    const query: Record<string, string | number> = {
      page: params.page,
      per_page: params.per_page,
    };
    if (params.search) query['filter[search]'] = params.search;
    const { data } = await http.get<ApiSuccess<RoleData[]>>('/admin/roles', { params: query });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async get(id: number): Promise<RoleData> {
    const { data } = await http.get<ApiSuccess<RoleData>>(`/admin/roles/${id}`);
    return data.data;
  },

  async create(payload: RoleUpsertPayload): Promise<string> {
    const { data } = await http.post<ApiSuccess<RoleData>>('/admin/roles', payload);
    return data.message;
  },

  async update(id: number, payload: RoleUpsertPayload): Promise<string> {
    const { data } = await http.put<ApiSuccess<RoleData>>(`/admin/roles/${id}`, payload);
    return data.message;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/roles/${id}`);
    return data.message;
  },
};
