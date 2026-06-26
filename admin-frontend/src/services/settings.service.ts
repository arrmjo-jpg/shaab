import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  GeneralSettingsData,
  GeneralUpdatePayload,
  MediaAssetResponse,
  MediaStorageSettingsData,
  MediaStorageStatus,
  MediaStorageUpdatePayload,
} from '@/types/settings.types';

export const settingsService = {
  async getGeneral(): Promise<GeneralSettingsData> {
    const { data } = await http.get<ApiSuccess<GeneralSettingsData>>('/admin/settings/general');
    return data.data;
  },

  async updateGeneral(payload: GeneralUpdatePayload): Promise<string> {
    const { data } = await http.put<ApiSuccess<unknown>>('/admin/settings/general', payload);
    return data.message;
  },

  async testMail(to: string): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>('/admin/settings/mail/test', { to });
    return data.message;
  },

  async uploadBranding(files: Record<string, File>): Promise<MediaAssetResponse[]> {
    const form = new FormData();
    for (const [key, file] of Object.entries(files)) form.append(key, file);
    // لا تضبط Content-Type يدوياً — يجب أن يولّد المتصفح boundary لـ multipart
    const { data } = await http.post<ApiSuccess<MediaAssetResponse[]>>(
      '/admin/settings/media/branding',
      form,
    );
    return data.data;
  },

  // ─── Hybrid media storage (remote mirror) ────────────────────────────────

  async getMediaStorage(): Promise<MediaStorageStatus> {
    const { data } = await http.get<ApiSuccess<MediaStorageStatus>>(
      '/admin/settings/media-storage',
    );
    return data.data;
  },

  async updateMediaStorage(payload: MediaStorageUpdatePayload): Promise<string> {
    const { data } = await http.put<ApiSuccess<MediaStorageSettingsData>>(
      '/admin/settings/media-storage',
      payload,
    );
    return data.message;
  },

  async testMediaStorage(payload: MediaStorageUpdatePayload): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      '/admin/settings/media-storage/test',
      payload,
    );
    return data.message;
  },

  async syncMediaStorage(): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>('/admin/settings/media-storage/sync');
    return data.message;
  },
};
