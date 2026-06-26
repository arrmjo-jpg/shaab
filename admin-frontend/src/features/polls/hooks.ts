import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { pollsService } from '@/services/polls.service';
import { useToast } from '@/hooks/useToast';
import type { AnalyticsRangeKey } from '@/types/analytics.types';
import type { NormalizedError } from '@/types/api';
import type { PollsListParams, PollUpsertPayload } from '@/types/polls.types';

/** مساحة مفاتيح موحّدة للنطاق — إبطال واحد يطال كل قوائم/تفاصيل الاستطلاعات. */
const POLLS = ['polls'] as const;

function usePollsInvalidate() {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: POLLS });
}

export function usePolls(params: PollsListParams) {
  return useQuery({
    queryKey: [...POLLS, 'list', params],
    queryFn: () => pollsService.list(params),
  });
}

export function usePoll(id: number | null) {
  return useQuery({
    queryKey: [...POLLS, 'detail', id],
    queryFn: () => pollsService.get(id as number),
    enabled: id !== null,
  });
}

/** تحليلات أسطول الاستطلاعات (مؤشّرات/متصدّرون) — مدى كامل. */
export function usePollAnalytics() {
  return useQuery({
    queryKey: [...POLLS, 'analytics', 'fleet'],
    queryFn: () => pollsService.analytics(),
  });
}

/** تحليلات استطلاع واحد (سياقيّة) — مفتاح مُعامَل بالمُعرّف + النطاق. */
export function usePollEntityAnalytics(
  id: number | null,
  range: AnalyticsRangeKey,
  from?: string,
  to?: string,
) {
  return useQuery({
    queryKey: [...POLLS, 'analytics', 'entity', id, range, from ?? null, to ?? null],
    queryFn: () => pollsService.entityAnalytics(id as number, range, from, to),
    enabled: id !== null,
  });
}

export function useCreatePoll() {
  const invalidate = usePollsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: PollUpsertPayload) => pollsService.create(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useUpdatePoll() {
  const invalidate = usePollsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; payload: PollUpsertPayload }) => pollsService.update(v.id, v.payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useToggleActivePoll() {
  const invalidate = usePollsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (id: number) => pollsService.toggleActive(id),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeletePoll() {
  const invalidate = usePollsInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => pollsService.remove(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useRestorePoll() {
  const invalidate = usePollsInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => pollsService.restore(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useForceDeletePoll() {
  const invalidate = usePollsInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => pollsService.forceDelete(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}
