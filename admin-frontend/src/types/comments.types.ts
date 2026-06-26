import type { PaginationMeta } from '@/types/users.types';

/** أنواع إشراف التعليقات (لوحة الإدارة) — تطابق عقد CommentResource (Backend Slice 1/2). */

export type CommentStatus = 'pending' | 'approved' | 'rejected' | 'spam';

/** حالات الإشراف الهدف (تستبعد pending — لا يُنتقَل إليها). */
export type ModerationStatus = 'approved' | 'rejected' | 'spam';

export interface AdminComment {
  id: number;
  body: string;
  status: CommentStatus;
  parent_id: number | null;
  commentable_type: string;
  commentable_id: number;
  author: {
    user_id: number | null;
    name: string | null;
    is_guest: boolean;
  };
  created_at: string | null;
}

export interface CommentsListParams {
  page: number;
  per_page: number;
  status: '' | CommentStatus;
  q: string;
}

export interface CommentsListResult {
  data: AdminComment[];
  pagination: PaginationMeta;
}
