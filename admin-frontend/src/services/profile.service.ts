import { http } from './http/client';
import { usersService } from './users.service';
import type { ApiSuccess } from '@/types/api';
import type { PaginationMeta } from '@/types/users.types';
import type {
  ProfileData,
  ProfileUpdatePayload,
  ChangePasswordPayload,
  ProfileActivity,
  ProfileActivityQuery,
  ProfileActivityResult,
  ProfileAnalytics,
  ProfilePermissions,
  ProfileSecurity,
  ProfileSession,
} from '@/types/profile.types';

export const profileService = {
  async get(): Promise<ProfileData> {
    const { data } = await http.get<ApiSuccess<ProfileData>>('/admin/profile');
    return data.data;
  },

  async update(payload: ProfileUpdatePayload): Promise<string> {
    const { data } = await http.put<ApiSuccess<ProfileData>>('/admin/profile', payload);
    return data.message;
  },

  async changePassword(payload: ChangePasswordPayload): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      '/admin/profile/password',
      payload,
    );
    return data.message;
  },

  async activity(params: ProfileActivityQuery = {}): Promise<ProfileActivityResult> {
    const { data } = await http.get<ApiSuccess<ProfileActivity[]>>(
      '/admin/profile/activity',
      { params },
    );
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async analytics(): Promise<ProfileAnalytics> {
    const { data } = await http.get<ApiSuccess<ProfileAnalytics>>('/admin/profile/analytics');
    return data.data;
  },

  async permissions(): Promise<ProfilePermissions> {
    const { data } = await http.get<ApiSuccess<ProfilePermissions>>('/admin/profile/permissions');
    return data.data;
  },

  async security(): Promise<ProfileSecurity> {
    const { data } = await http.get<ApiSuccess<ProfileSecurity>>('/admin/profile/security');
    return data.data;
  },

  async sessions(): Promise<ProfileSession[]> {
    const { data } = await http.get<ApiSuccess<ProfileSession[]>>(
      '/admin/profile/sessions',
    );
    return data.data;
  },

  async revokeSession(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(
      `/admin/profile/sessions/${id}`,
    );
    return data.message;
  },

  async revokeOtherSessions(): Promise<string> {
    const { data } = await http.post<ApiSuccess<{ revoked: number }>>(
      '/admin/profile/sessions/revoke-others',
    );
    return data.message;
  },

  uploadAvatar: usersService.uploadAvatar,
};
