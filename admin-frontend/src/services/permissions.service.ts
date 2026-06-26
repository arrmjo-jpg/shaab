import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  PermissionGroupBlock,
  PermissionGroupData,
  PermissionGroupUpsertPayload,
} from '@/types/rbac.types';

export const permissionsService = {
  /** صلاحيات مجمّعة (للعرض في مستكشف الصلاحيات وإسناد الأدوار). */
  async listGrouped(): Promise<PermissionGroupBlock[]> {
    const { data } = await http.get<ApiSuccess<PermissionGroupBlock[]>>('/admin/permissions');
    return data.data;
  },

  /** مجموعات الصلاحيات كـ كيانات حقيقية. */
  async groups(): Promise<PermissionGroupData[]> {
    const { data } = await http.get<ApiSuccess<PermissionGroupData[]>>(
      '/admin/permission-groups',
    );
    return data.data;
  },

  async createGroup(payload: PermissionGroupUpsertPayload): Promise<string> {
    const { data } = await http.post<ApiSuccess<PermissionGroupData>>(
      '/admin/permission-groups',
      payload,
    );
    return data.message;
  },

  async updateGroup(id: number, payload: PermissionGroupUpsertPayload): Promise<string> {
    const { data } = await http.put<ApiSuccess<PermissionGroupData>>(
      `/admin/permission-groups/${id}`,
      payload,
    );
    return data.message;
  },

  async removeGroup(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(
      `/admin/permission-groups/${id}`,
    );
    return data.message;
  },
};
