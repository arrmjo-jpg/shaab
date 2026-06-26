import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  ExternalVideoResolved,
  MediaAssetData,
  MediaLibraryListParams,
  MediaLibraryListResult,
  MediaMetadataPayload,
} from '@/types/content.types';
import type { PaginationMeta } from '@/types/users.types';

/** Upload progress callback (0–100). */
export type ProgressFn = (percent: number) => void;

/**
 * Central media library — upload a clean source asset and browse the library
 * for reuse. Assets are independent of any article (attached on article save).
 */
export const mediaLibraryService = {
  async upload(file: File, onProgress?: ProgressFn, profile?: string): Promise<MediaAssetData> {
    const form = new FormData();
    form.append('file', file);
    // ملف معالجة اختياري محايد للمحتوى (مثل reel) — نفس مسار الرفع، لا API منفصل.
    if (profile) {
      form.append('profile', profile);
    }

    const { data } = await http.post<ApiSuccess<MediaAssetData>>('/admin/media', form, {
      onUploadProgress: (e) => {
        if (onProgress && e.total) {
          onProgress(Math.round((e.loaded / e.total) * 100));
        }
      },
    });
    return data.data;
  },

  async list(params: MediaLibraryListParams): Promise<MediaLibraryListResult> {
    const query: Record<string, string | number> = {};
    if (params.type) query.type = params.type;
    if (params.provider) query.provider = params.provider;
    if (params.search) query.search = params.search;
    if (params.page) query.page = params.page;
    if (params.per_page) query.per_page = params.per_page;

    const { data } = await http.get<ApiSuccess<MediaAssetData[]>>('/admin/media', {
      params: query,
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  /** Fetch a single asset by uuid — detail view incl. where-used + poll status. */
  async get(uuid: string): Promise<MediaAssetData> {
    const { data } = await http.get<ApiSuccess<MediaAssetData>>(`/admin/media/${uuid}`);
    return data.data;
  },

  /** Edit editorial metadata (alt/caption/credit/source) without re-uploading. */
  async update(uuid: string, payload: MediaMetadataPayload): Promise<MediaAssetData> {
    const { data } = await http.patch<ApiSuccess<MediaAssetData>>(`/admin/media/${uuid}`, payload);
    return data.data;
  },

  /**
   * Delete an asset. In-use assets return 409 unless `force` is passed —
   * the caller surfaces a force-confirmation from the NormalizedError.
   */
  async remove(uuid: string, force = false): Promise<string> {
    const { data } = await http.delete<ApiSuccess<null>>(`/admin/media/${uuid}`, {
      params: force ? { force: 1 } : {},
    });
    return data.message;
  },

  /** Re-queue derivative generation for all image library assets. */
  async regenerateDerivatives(): Promise<{ queued: number; message: string }> {
    const { data } = await http.post<ApiSuccess<{ queued: number }>>(
      '/admin/media/regenerate-derivatives',
    );
    return { queued: data.data.queued, message: data.message };
  },

  /** Retry processing for a single asset (failed → queued). */
  async reprocess(uuid: string): Promise<MediaAssetData> {
    const { data } = await http.post<ApiSuccess<MediaAssetData>>(`/admin/media/${uuid}/reprocess`);
    return data.data;
  },

  /** Preview an external-video URL (provider detection) without persisting. */
  async resolveExternal(url: string): Promise<ExternalVideoResolved> {
    const { data } = await http.post<ApiSuccess<ExternalVideoResolved>>(
      '/admin/media/external/resolve',
      { url },
    );
    return data.data;
  },

  /** Create an external video as a central media asset. */
  async storeExternal(url: string): Promise<MediaAssetData> {
    const { data } = await http.post<ApiSuccess<MediaAssetData>>('/admin/media/external', { url });
    return data.data;
  },
};
