import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { teamMembersService } from '@/services/teamMembers.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type {
  TeamMemberData,
  TeamMembersListParams,
  TeamMemberUpsertPayload,
} from '@/types/team.types';

const TEAM = ['team-members'] as const;

function useTeamInvalidate() {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: TEAM });
}

export function useTeamMembers(params: TeamMembersListParams) {
  return useQuery({
    queryKey: [...TEAM, params],
    queryFn: () => teamMembersService.list(params),
  });
}

export function useTeamMember(id: number | null) {
  return useQuery({
    queryKey: [...TEAM, 'detail', id],
    queryFn: () => teamMembersService.get(id as number),
    enabled: id !== null,
  });
}

export function useCreateTeamMember() {
  const invalidate = useTeamInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: TeamMemberUpsertPayload) => teamMembersService.create(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useUpdateTeamMember() {
  const invalidate = useTeamInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; payload: TeamMemberUpsertPayload }) =>
      teamMembersService.update(v.id, v.payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useToggleTeamMemberStatus() {
  const invalidate = useTeamInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; status: TeamMemberData['status'] }) =>
      teamMembersService.toggleStatus(v.id, v.status),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useReorderTeamMembers() {
  const invalidate = useTeamInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (ids: number[]) => teamMembersService.reorder(ids),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteTeamMember() {
  const invalidate = useTeamInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => teamMembersService.remove(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useRestoreTeamMember() {
  const invalidate = useTeamInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => teamMembersService.restore(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useForceDeleteTeamMember() {
  const invalidate = useTeamInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => teamMembersService.forceDelete(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}
