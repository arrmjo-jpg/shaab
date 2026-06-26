import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { wpMigrationService } from '@/services/wpMigration.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type {
  CategoryMapInput,
  ConflictPolicy,
  ConnectionPayload,
  MigrationItemStatus,
  MigrationStats,
  RetryPayload,
} from '@/types/wpMigration.types';

const WPM = ['wp-migration'] as const;

export function useMigrationRuns() {
  return useQuery({
    queryKey: [...WPM, 'runs'],
    queryFn: () => wpMigrationService.listRuns(),
  });
}

export function useMigrationRun(id: number | null) {
  return useQuery({
    queryKey: [...WPM, 'run', id],
    queryFn: () => wpMigrationService.getRun(id as number),
    enabled: id !== null,
  });
}

function useWpmInvalidate() {
  const qc = useQueryClient();

  return () => void qc.invalidateQueries({ queryKey: WPM });
}

export function useTestConnection() {
  const { error } = useToast();

  return useMutation({
    mutationFn: (payload: ConnectionPayload) => wpMigrationService.testConnection(payload),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useCreateRun() {
  const invalidate = useWpmInvalidate();
  const { error } = useToast();

  return useMutation({
    mutationFn: (payload: ConnectionPayload) => wpMigrationService.createRun(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useAuditRun() {
  const invalidate = useWpmInvalidate();
  const { error } = useToast();

  return useMutation({
    mutationFn: (id: number) => wpMigrationService.audit(id),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useRunCategories(runId: number) {
  return useQuery({
    queryKey: [...WPM, 'categories', runId],
    queryFn: () => wpMigrationService.getCategories(runId),
  });
}

export function useTargetCategories(runId: number) {
  return useQuery({
    queryKey: [...WPM, 'target-categories', runId],
    queryFn: () => wpMigrationService.getTargetCategories(runId),
  });
}

export function useSaveCategoryMaps(runId: number) {
  const invalidate = useWpmInvalidate();
  const { error } = useToast();

  return useMutation({
    mutationFn: (maps: CategoryMapInput[]) => wpMigrationService.saveCategoryMaps(runId, maps),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useImportTaxonomy(runId: number) {
  const invalidate = useWpmInvalidate();
  const { error } = useToast();

  return useMutation({
    mutationFn: () => wpMigrationService.importTaxonomy(runId),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useGeneratePreview(runId: number) {
  const invalidate = useWpmInvalidate();
  const { error } = useToast();

  return useMutation({
    mutationFn: () => wpMigrationService.generatePreview(runId),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useApproveRun(runId: number) {
  const invalidate = useWpmInvalidate();
  const { error } = useToast();

  return useMutation({
    mutationFn: (policy: ConflictPolicy) => wpMigrationService.approveRun(runId, policy),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ─── Execution dashboard (Steps 7–9) ─────────────────────────────────────────

const ACTIVE: MigrationStats['status'][] = ['running', 'paused', 'stopping'];

/** لقطة حيّة — تستطلع كل 4ث ما دامت التشغيلة فعّالة، وتتوقّف عند الانتهاء. */
export function useMigrationStats(runId: number | null, enabled = true) {
  return useQuery({
    queryKey: [...WPM, 'stats', runId],
    queryFn: () => wpMigrationService.getStats(runId as number),
    enabled: runId !== null && enabled,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status && ACTIVE.includes(status) ? 4000 : false;
    },
  });
}

export function useMigrationReport(runId: number | null, enabled = true) {
  return useQuery({
    queryKey: [...WPM, 'report', runId],
    queryFn: () => wpMigrationService.getReport(runId as number),
    enabled: runId !== null && enabled,
  });
}

export function useMigrationItems(
  runId: number | null,
  status: MigrationItemStatus,
  page: number,
) {
  return useQuery({
    queryKey: [...WPM, 'items', runId, status, page],
    queryFn: () => wpMigrationService.getItems(runId as number, { status, page }),
    enabled: runId !== null,
  });
}

function useLifecycleMutation(fn: (id: number) => Promise<unknown>) {
  const invalidate = useWpmInvalidate();
  const { error } = useToast();

  return useMutation({
    mutationFn: (id: number) => fn(id),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useStartRun() {
  const invalidate = useWpmInvalidate();
  const { error } = useToast();

  return useMutation({
    mutationFn: (vars: { id: number; incremental?: boolean }) =>
      wpMigrationService.start(vars.id, vars.incremental ?? false),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function usePauseRun() {
  return useLifecycleMutation((id) => wpMigrationService.pause(id));
}

export function useResumeRun() {
  return useLifecycleMutation((id) => wpMigrationService.resume(id));
}

export function useStopRun() {
  return useLifecycleMutation((id) => wpMigrationService.stop(id));
}

// ⚠️ TEMPORARY FEATURE — Quick Incremental Import — Remove before Production release.
// TODO(production): احذف هذا الـhook عند إزالة الاختصار.
export function useQuickIncremental() {
  return useLifecycleMutation((id) => wpMigrationService.quickIncremental(id));
}

export function useRetryItems(runId: number) {
  const invalidate = useWpmInvalidate();
  const { error } = useToast();

  return useMutation({
    mutationFn: (payload: RetryPayload) => wpMigrationService.retry(runId, payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}
