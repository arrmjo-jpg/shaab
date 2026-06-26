import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { pagesService } from '@/services/pages.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type { PagesListParams, PageUpsertPayload } from '@/types/content.types';

const PAGES = ['pages'] as const;

function usePagesInvalidate() {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: PAGES });
}

export function usePages(params: PagesListParams) {
  return useQuery({
    queryKey: [...PAGES, params],
    queryFn: () => pagesService.list(params),
  });
}

export function usePage(id: number | null) {
  return useQuery({
    queryKey: [...PAGES, 'detail', id],
    queryFn: () => pagesService.get(id as number),
    enabled: id !== null,
  });
}

export function useCreatePage() {
  const invalidate = usePagesInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: PageUpsertPayload) => pagesService.create(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useUpdatePage() {
  const invalidate = usePagesInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; payload: PageUpsertPayload }) =>
      pagesService.update(v.id, v.payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useTransitionPage() {
  const invalidate = usePagesInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; status: string; publishedAt?: string | null }) =>
      pagesService.transition(v.id, v.status, v.publishedAt),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeletePage() {
  const invalidate = usePagesInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => pagesService.remove(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useRestorePage() {
  const invalidate = usePagesInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => pagesService.restore(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useForceDeletePage() {
  const invalidate = usePagesInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => pagesService.forceDelete(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}
