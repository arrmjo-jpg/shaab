import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { PaginationMeta } from '@/types/users.types';
import type {
  WriterRequestData,
  WriterRequestsListParams,
  WriterRequestsListResult,
} from '@/types/writer.types';

export const writerRequestsService = {
  async list(p: WriterRequestsListParams): Promise<WriterRequestsListResult> {
    const params: Record<string, string | number> = {
      page: p.page,
      per_page: p.per_page,
    };
    if (p.search) params['filter[search]'] = p.search;
    if (p.status) params['filter[status]'] = p.status;

    const { data } = await http.get<ApiSuccess<WriterRequestData[]>>(
      '/admin/writer-requests',
      { params },
    );
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async approve(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      `/admin/writer-requests/${id}/approve`,
    );
    return data.message;
  },

  async reject(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      `/admin/writer-requests/${id}/reject`,
    );
    return data.message;
  },
};
