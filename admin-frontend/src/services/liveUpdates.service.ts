import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  LiveUpdateCreatePayload,
  LiveUpdateData,
  LiveUpdatesListResult,
  LiveUpdateUpdatePayload,
} from '@/types/content.types';
import type { PaginationMeta } from '@/types/users.types';

export const liveUpdatesService = {
  async list(articleId: number, page: number, perPage = 30): Promise<LiveUpdatesListResult> {
    const { data } = await http.get<ApiSuccess<LiveUpdateData[]>>(
      `/admin/articles/${articleId}/live-updates`,
      { params: { page, per_page: perPage } },
    );
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async create(articleId: number, payload: LiveUpdateCreatePayload): Promise<LiveUpdateData> {
    const { data } = await http.post<ApiSuccess<LiveUpdateData>>(
      `/admin/articles/${articleId}/live-updates`,
      payload,
    );
    return data.data;
  },

  async update(
    articleId: number,
    updateId: number,
    payload: LiveUpdateUpdatePayload,
  ): Promise<LiveUpdateData> {
    const { data } = await http.put<ApiSuccess<LiveUpdateData>>(
      `/admin/articles/${articleId}/live-updates/${updateId}`,
      payload,
    );
    return data.data;
  },

  async remove(articleId: number, updateId: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(
      `/admin/articles/${articleId}/live-updates/${updateId}`,
    );
    return data.message;
  },

  /** Move an update up/down the timeline (swaps position with its neighbor). */
  async move(
    articleId: number,
    updateId: number,
    direction: 'up' | 'down',
  ): Promise<LiveUpdateData> {
    const { data } = await http.patch<ApiSuccess<LiveUpdateData>>(
      `/admin/articles/${articleId}/live-updates/${updateId}/move`,
      { direction },
    );
    return data.data;
  },
};
