import {
  useMutation,
  useQuery,
  useQueryClient,
  keepPreviousData,
} from '@tanstack/react-query';
import { profileService } from '@/services/profile.service';
import { useToast } from '@/hooks/useToast';
import { useAuth } from '@/hooks/useAuth';
import type { NormalizedError } from '@/types/api';
import type {
  ProfileUpdatePayload,
  ChangePasswordPayload,
  ProfileActivityQuery,
} from '@/types/profile.types';

const PROFILE = ['profile'] as const;
const ACTIVITY = ['profile', 'activity'] as const;
const SESSIONS = ['profile', 'sessions'] as const;
const ANALYTICS = ['profile', 'analytics'] as const;
const PERMISSIONS = ['profile', 'permissions'] as const;
const SECURITY = ['profile', 'security'] as const;

export function useProfile() {
  return useQuery({ queryKey: PROFILE, queryFn: () => profileService.get() });
}

export function useUpdateProfile() {
  const qc = useQueryClient();
  const { hydrate } = useAuth();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (payload: ProfileUpdatePayload) => profileService.update(payload),
    onSuccess: (m) => {
      success(m);
      void qc.invalidateQueries({ queryKey: PROFILE });
      void hydrate(); // حدّث التوب-بار (الاسم/الصورة)
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useChangePassword() {
  const qc = useQueryClient();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (payload: ChangePasswordPayload) =>
      profileService.changePassword(payload),
    onSuccess: (m) => {
      success(m);
      void qc.invalidateQueries({ queryKey: SECURITY });
      void qc.invalidateQueries({ queryKey: SESSIONS });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useProfileActivity(params: ProfileActivityQuery) {
  return useQuery({
    queryKey: [...ACTIVITY, params],
    queryFn: () => profileService.activity(params),
    placeholderData: keepPreviousData,
  });
}

export function useProfileAnalytics() {
  return useQuery({ queryKey: ANALYTICS, queryFn: () => profileService.analytics() });
}

export function useProfilePermissions() {
  return useQuery({ queryKey: PERMISSIONS, queryFn: () => profileService.permissions() });
}

export function useProfileSecurity() {
  return useQuery({ queryKey: SECURITY, queryFn: () => profileService.security() });
}

export function useSessions() {
  return useQuery({ queryKey: SESSIONS, queryFn: () => profileService.sessions() });
}

export function useRevokeSession() {
  const qc = useQueryClient();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => profileService.revokeSession(id),
    onSuccess: (m) => {
      success(m);
      void qc.invalidateQueries({ queryKey: SESSIONS });
      void qc.invalidateQueries({ queryKey: SECURITY });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useRevokeOtherSessions() {
  const qc = useQueryClient();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: () => profileService.revokeOtherSessions(),
    onSuccess: (m) => {
      success(m);
      void qc.invalidateQueries({ queryKey: SESSIONS });
      void qc.invalidateQueries({ queryKey: SECURITY });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useUploadProfileAvatar() {
  const { error } = useToast();
  return useMutation({
    mutationFn: (file: File) => profileService.uploadAvatar(file),
    onError: (e: NormalizedError) => error(e.message),
  });
}
