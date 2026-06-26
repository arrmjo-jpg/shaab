import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  AdminComment,
  CommentsListParams,
  CommentsListResult,
  ModerationStatus,
} from '@/types/comments.types';
import type { PaginationMeta } from '@/types/users.types';

function buildParams(p: CommentsListParams): Record<string, string | number> {
  const params: Record<string, string | number> = { page: p.page, per_page: p.per_page };
  if (p.status) params.status = p.status;
  if (p.q) params.q = p.q;
  return params;
}

/**
 * إشراف التعليقات — يستهلك نقاط Slice 1/2 القائمة فقط (لا تغيير API).
 */
export const commentsService = {
  async list(p: CommentsListParams): Promise<CommentsListResult> {
    const { data } = await http.get<ApiSuccess<AdminComment[]>>('/admin/comments', {
      params: buildParams(p),
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  /** اعتماد/رفض/سبام — comments.approve. */
  async moderate(id: number, status: ModerationStatus): Promise<string> {
    const { data } = await http.patch<ApiSuccess<AdminComment>>(
      `/admin/comments/${id}/status`,
      { status },
    );
    return data.message;
  },

  /** حذف ناعم — comments.delete. */
  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/comments/${id}`);
    return data.message;
  },
};
