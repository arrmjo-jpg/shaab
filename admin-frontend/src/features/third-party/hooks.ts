import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { thirdPartyService } from '@/services/thirdParty.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type { ThirdPartyUpdatePayload } from '@/types/thirdParty.types';

const KEY = ['settings', 'third_party'] as const;

export function useThirdParty() {
  return useQuery({ queryKey: KEY, queryFn: () => thirdPartyService.get() });
}

export function useUpdateThirdParty() {
  const qc = useQueryClient();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (payload: ThirdPartyUpdatePayload) => thirdPartyService.update(payload),
    onSuccess: (message) => {
      success(message);
      void qc.invalidateQueries({ queryKey: KEY });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useUploadFirebase() {
  const qc = useQueryClient();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (file: File) => thirdPartyService.uploadFirebase(file),
    onSuccess: (message) => {
      success(message);
      void qc.invalidateQueries({ queryKey: KEY });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useTestSportmonks() {
  const { success, error } = useToast();
  return useMutation({
    mutationFn: () => thirdPartyService.testSportmonks(),
    onSuccess: (m) => success(m),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useTestOpenweather() {
  const { success, error } = useToast();
  return useMutation({
    mutationFn: () => thirdPartyService.testOpenweather(),
    onSuccess: (m) => success(m),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useTestWhatsapp() {
  const { success, error } = useToast();
  return useMutation({
    mutationFn: () => thirdPartyService.testWhatsapp(),
    onSuccess: (m) => success(m),
    onError: (e: NormalizedError) => error(e.message),
  });
}
