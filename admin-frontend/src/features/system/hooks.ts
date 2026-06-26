import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { schedulerService } from '@/services/scheduler.service';
import { systemService } from '@/services/system.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type { UpdateScheduledTaskPayload } from '@/types/scheduler.types';
import type { FailedJobsQuery, ManageFailedJobsPayload } from '@/types/system.types';

const LIST_KEY = ['system', 'scheduler'] as const;
const OPS_KEY = ['system', 'ops-overview'] as const;
const FAILED_KEY = ['system', 'failed-jobs'] as const;
const DIAGNOSTICS_KEY = ['system', 'diagnostics'] as const;

export function useScheduledTasks() {
  return useQuery({ queryKey: LIST_KEY, queryFn: () => schedulerService.list() });
}

function useInvalidate() {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: LIST_KEY });
}

export function useUpdateScheduledTask() {
  const invalidate = useInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { key: string; payload: UpdateScheduledTaskPayload }) =>
      schedulerService.update(v.key, v.payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useRunScheduledTask() {
  const invalidate = useInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (key: string) => schedulerService.run(key),
    onSuccess: (task) => {
      invalidate();
      return task;
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ─── Operational overview ───────────────────────────────────────────────────

export function useOpsOverview() {
  return useQuery({
    queryKey: OPS_KEY,
    queryFn: () => systemService.opsOverview(),
    refetchInterval: 30_000, // لقطة شبه حيّة للمشغّل
  });
}

// ─── Failed jobs ─────────────────────────────────────────────────────────────

export function useFailedJobs(params: FailedJobsQuery) {
  return useQuery({
    queryKey: [...FAILED_KEY, params],
    queryFn: () => systemService.failedJobs(params),
  });
}

function useInvalidateFailed() {
  const qc = useQueryClient();
  return () => {
    void qc.invalidateQueries({ queryKey: FAILED_KEY });
    void qc.invalidateQueries({ queryKey: OPS_KEY });
  };
}

export function useRetryFailedJobs() {
  const invalidate = useInvalidateFailed();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (payload: ManageFailedJobsPayload) => systemService.retryFailedJobs(payload),
    onSuccess: (message) => {
      success(message);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteFailedJobs() {
  const invalidate = useInvalidateFailed();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (payload: ManageFailedJobsPayload) => systemService.deleteFailedJobs(payload),
    onSuccess: (message) => {
      success(message);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ─── Diagnostics ──────────────────────────────────────────────────────────────

export function useDiagnostics() {
  return useQuery({
    queryKey: DIAGNOSTICS_KEY,
    queryFn: () => systemService.diagnostics(),
    refetchInterval: 30_000, // لقطة شبه حيّة للمشغّل
  });
}

// ─── Clear content cache ───────────────────────────────────────────────────────

export function useClearContentCache() {
  const { error } = useToast();
  return useMutation({
    mutationFn: () => systemService.clearContentCache(),
    onError: (e: NormalizedError) => error(e.message),
  });
}
