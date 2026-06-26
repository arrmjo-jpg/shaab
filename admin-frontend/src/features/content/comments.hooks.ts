import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { commentsService } from '@/services/comments.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type { CommentsListParams, ModerationStatus } from '@/types/comments.types';

const COMMENTS = ['comments'] as const;

export function useComments(params: CommentsListParams) {
  return useQuery({
    queryKey: [...COMMENTS, params],
    queryFn: () => commentsService.list(params),
    placeholderData: keepPreviousData,
  });
}

function useCommentsInvalidate() {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: COMMENTS });
}

/** اعتماد/رفض/سبام — يُبطِل القائمة + رسالة الخادم. */
export function useModerateComment() {
  const invalidate = useCommentsInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; status: ModerationStatus }) =>
      commentsService.moderate(v.id, v.status),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteComment() {
  const invalidate = useCommentsInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => commentsService.remove(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}
