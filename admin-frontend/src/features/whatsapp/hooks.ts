import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { whatsappService } from '@/services/whatsapp.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type {
  WhatsappCampaignCreatePayload,
  WhatsappCampaignsListParams,
  WhatsappContactsListParams,
  WhatsappContactUpsertPayload,
  WhatsappGroupUpsertPayload,
  WhatsappImportPayload,
} from '@/types/whatsapp.types';

/** مساحة مفاتيح موحّدة للنطاق — إبطال واحد يطال المجموعات وجهات الاتصال معاً. */
const WA = ['whatsapp'] as const;

function useWaInvalidate() {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: WA });
}

// ─── المجموعات ──────────────────────────────────────────────

export function useWhatsappGroups() {
  return useQuery({ queryKey: [...WA, 'groups'], queryFn: () => whatsappService.listGroups() });
}

export function useCreateWhatsappGroup() {
  const invalidate = useWaInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: WhatsappGroupUpsertPayload) => whatsappService.createGroup(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useUpdateWhatsappGroup() {
  const invalidate = useWaInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; payload: WhatsappGroupUpsertPayload }) =>
      whatsappService.updateGroup(v.id, v.payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteWhatsappGroup() {
  const invalidate = useWaInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => whatsappService.removeGroup(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ─── جهات الاتصال ──────────────────────────────────────────

export function useWhatsappContacts(params: WhatsappContactsListParams) {
  return useQuery({
    queryKey: [...WA, 'contacts', params],
    queryFn: () => whatsappService.listContacts(params),
  });
}

export function useCreateWhatsappContact() {
  const invalidate = useWaInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: WhatsappContactUpsertPayload) => whatsappService.createContact(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useUpdateWhatsappContact() {
  const invalidate = useWaInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; payload: WhatsappContactUpsertPayload }) =>
      whatsappService.updateContact(v.id, v.payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteWhatsappContact() {
  const invalidate = useWaInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => whatsappService.removeContact(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useImportWhatsappContacts() {
  const invalidate = useWaInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: WhatsappImportPayload) => whatsappService.importContacts(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ─── الحملات ──────────────────────────────────────────────

export function useWhatsappCampaigns(params: WhatsappCampaignsListParams) {
  return useQuery({
    queryKey: [...WA, 'campaigns', params],
    queryFn: () => whatsappService.listCampaigns(params),
  });
}

export function useWhatsappCampaign(id: number | null) {
  return useQuery({
    queryKey: [...WA, 'campaigns', 'detail', id],
    queryFn: () => whatsappService.getCampaign(id as number),
    enabled: id !== null,
  });
}

export function useCreateWhatsappCampaign() {
  const invalidate = useWaInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: WhatsappCampaignCreatePayload) => whatsappService.createCampaign(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteWhatsappCampaign() {
  const invalidate = useWaInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => whatsappService.removeCampaign(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useSendWhatsappCampaign() {
  const invalidate = useWaInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (id: number) => whatsappService.sendCampaign(id),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useCancelWhatsappCampaign() {
  const invalidate = useWaInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (id: number) => whatsappService.cancelCampaign(id),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useWhatsappCampaignMessages(
  id: number | null,
  params: { page: number; per_page: number; status: '' | 'pending' | 'sent' | 'failed' },
) {
  return useQuery({
    queryKey: [...WA, 'campaigns', 'messages', id, params],
    queryFn: () => whatsappService.campaignMessages(id as number, params),
    enabled: id !== null,
  });
}
