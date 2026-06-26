import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { cdnService } from '@/services/cdn.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type { CdnUpdatePayload } from '@/types/cdn.types';

const STATUS_KEY = ['cdn', 'status'] as const;
const SETTINGS_KEY = ['cdn', 'settings'] as const;

export function useCdnStatus() {
  return useQuery({ queryKey: STATUS_KEY, queryFn: () => cdnService.status() });
}

export function useCdnSettings() {
  return useQuery({ queryKey: SETTINGS_KEY, queryFn: () => cdnService.getSettings() });
}

function useInvalidate() {
  const qc = useQueryClient();
  return () => {
    void qc.invalidateQueries({ queryKey: STATUS_KEY });
    void qc.invalidateQueries({ queryKey: SETTINGS_KEY });
  };
}

export function useUpdateCdn() {
  const invalidate = useInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (payload: CdnUpdatePayload) => cdnService.update(payload),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useCdnTest() {
  const invalidate = useInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: () => cdnService.test(),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function usePurge() {
  const invalidate = useInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (urls: string[]) => cdnService.purge(urls),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function usePurgeAll() {
  const invalidate = useInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: () => cdnService.purgeAll(),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}
