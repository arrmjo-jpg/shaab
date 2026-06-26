import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { vertixService, type VertixStatus } from '@/services/vertix.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';

const KEY = ['vertix'] as const;

/** لقطة حيّة — تستطلع كل 3ث ما دامت إحدى المرحلتين تعمل، وتتوقّف عند الانتهاء. */
export function useVertixStatus() {
  return useQuery({
    queryKey: [...KEY, 'status'],
    queryFn: () => vertixService.status(),
    refetchInterval: (query) => {
      const d = query.state.data as VertixStatus | undefined;
      const active = d?.news.status === 'running' || d?.categories.status === 'running';
      return active ? 3000 : false;
    },
  });
}

function useVertixAction(fn: () => Promise<VertixStatus>) {
  const qc = useQueryClient();
  const { error } = useToast();

  return useMutation({
    mutationFn: fn,
    onSuccess: () => void qc.invalidateQueries({ queryKey: KEY }),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useImportVertixCategories() {
  return useVertixAction(() => vertixService.importCategories());
}

export function useImportVertixNews() {
  return useVertixAction(() => vertixService.importNews());
}

export function useStopVertixNews() {
  return useVertixAction(() => vertixService.stopNews());
}
