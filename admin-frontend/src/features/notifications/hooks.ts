import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationsService } from '@/services/notifications.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type {
  AudienceType,
  CampaignAction,
  CampaignsListParams,
  ComposeCampaignPayload,
  NotificationSettings,
  TemplatePayload,
  UpdateEventChannelPayload,
} from '@/types/notifications.types';

/** مساحة مفاتيح موحّدة — إبطال واحد يطال كلّ بيانات مركز الإشعارات. */
const NOTIFS = ['notifications'] as const;

function useNotifsInvalidate() {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: NOTIFS });
}

// ── Campaigns ──
export function useCampaigns(params: CampaignsListParams) {
  return useQuery({
    queryKey: [...NOTIFS, 'campaigns', 'list', params],
    queryFn: () => notificationsService.listCampaigns(params),
  });
}

export function useCampaign(uuid: string | null) {
  return useQuery({
    queryKey: [...NOTIFS, 'campaigns', 'detail', uuid],
    queryFn: () => notificationsService.getCampaign(uuid as string),
    enabled: uuid !== null,
  });
}

export function useCampaignSummary() {
  return useQuery({
    queryKey: [...NOTIFS, 'campaigns', 'summary'],
    queryFn: () => notificationsService.summary(),
  });
}

export function useComposeCampaign() {
  const invalidate = useNotifsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: ComposeCampaignPayload) => notificationsService.compose(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useCampaignLifecycle() {
  const invalidate = useNotifsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { uuid: string; action: CampaignAction }) =>
      notificationsService.lifecycle(v.uuid, v.action),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ── Event Matrix ──
export function useMatrix() {
  return useQuery({
    queryKey: [...NOTIFS, 'matrix'],
    queryFn: () => notificationsService.matrix(),
  });
}

export function useUpdateEventChannel() {
  const invalidate = useNotifsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; payload: UpdateEventChannelPayload }) =>
      notificationsService.updateChannel(v.id, v.payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useToggleEvent() {
  const invalidate = useNotifsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (id: number) => notificationsService.toggleEvent(id),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ── Templates ──
export function useTemplates(filters?: { event_key?: string; channel?: string }) {
  return useQuery({
    queryKey: [...NOTIFS, 'templates', 'list', filters ?? {}],
    queryFn: () => notificationsService.listTemplates(filters),
  });
}

export function useTemplateVariables(eventKey: string | null) {
  return useQuery({
    queryKey: [...NOTIFS, 'templates', 'variables', eventKey],
    queryFn: () => notificationsService.variables(eventKey as string),
    enabled: Boolean(eventKey),
  });
}

export function useCreateTemplate() {
  const invalidate = useNotifsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: TemplatePayload) => notificationsService.createTemplate(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useUpdateTemplate() {
  const invalidate = useNotifsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; payload: TemplatePayload }) =>
      notificationsService.updateTemplate(v.id, v.payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteTemplate() {
  const invalidate = useNotifsInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => notificationsService.deleteTemplate(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ── Audiences ──
export function useAudiences() {
  return useQuery({
    queryKey: [...NOTIFS, 'audiences'],
    queryFn: () => notificationsService.audiences(),
  });
}

export function useAudiencePreview(audience: AudienceType | null) {
  return useQuery({
    queryKey: [...NOTIFS, 'audience-preview', audience],
    queryFn: () => notificationsService.previewAudience(audience as AudienceType),
    enabled: audience !== null,
  });
}

// ── Channel Health ──
export function useHealth() {
  return useQuery({
    queryKey: [...NOTIFS, 'health'],
    queryFn: () => notificationsService.health(),
  });
}

export function useProbeChannels() {
  const invalidate = useNotifsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: () => notificationsService.probe(),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ── Settings ──
export function useNotificationSettings() {
  return useQuery({
    queryKey: [...NOTIFS, 'settings'],
    queryFn: () => notificationsService.getSettings(),
  });
}

export function useUpdateNotificationSettings() {
  const invalidate = useNotifsInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: NotificationSettings) => notificationsService.updateSettings(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}
