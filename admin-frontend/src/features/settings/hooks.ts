import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { settingsService } from '@/services/settings.service';
import { useToast } from '@/hooks/useToast';
import { queryKeys } from '@/lib/queryKeys';
import type { NormalizedError } from '@/types/api';
import type { GeneralUpdatePayload, MediaStorageUpdatePayload } from '@/types/settings.types';

export function useGeneralSettings(enabled = true) {
  return useQuery({
    queryKey: queryKeys.settings('general'),
    queryFn: () => settingsService.getGeneral(),
    enabled,
  });
}

export function useUpdateGeneral() {
  const qc = useQueryClient();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (payload: GeneralUpdatePayload) => settingsService.updateGeneral(payload),
    onSuccess: (message) => {
      success(message);
      void qc.invalidateQueries({ queryKey: queryKeys.settings('general') });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useTestMail() {
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (to: string) => settingsService.testMail(to),
    onSuccess: (message) => success(message),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useUploadBranding() {
  const qc = useQueryClient();
  const { error } = useToast();
  return useMutation({
    mutationFn: (files: Record<string, File>) => settingsService.uploadBranding(files),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.settings('general') });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ─── Hybrid media storage (remote mirror) ──────────────────────────────────

export function useMediaStorageStatus(enabled = true) {
  return useQuery({
    queryKey: queryKeys.settings('media-storage'),
    queryFn: () => settingsService.getMediaStorage(),
    enabled,
  });
}

export function useUpdateMediaStorage() {
  const qc = useQueryClient();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (payload: MediaStorageUpdatePayload) =>
      settingsService.updateMediaStorage(payload),
    onSuccess: (message) => {
      success(message);
      void qc.invalidateQueries({ queryKey: queryKeys.settings('media-storage') });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useTestMediaStorage() {
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (payload: MediaStorageUpdatePayload) => settingsService.testMediaStorage(payload),
    onSuccess: (message) => success(message),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useSyncMediaStorage() {
  const qc = useQueryClient();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: () => settingsService.syncMediaStorage(),
    onSuccess: (message) => {
      success(message);
      void qc.invalidateQueries({ queryKey: queryKeys.settings('media-storage') });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}
