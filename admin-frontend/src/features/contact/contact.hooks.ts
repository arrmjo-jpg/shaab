import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adRequestService, contactService, inboxService } from '@/services/inbox.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type {
  AdListParams,
  AdStatusTarget,
  ContactListParams,
  ContactStatusTarget,
} from '@/types/inbox.types';

const INBOX = ['inbox'] as const;
const CONTACT = ['contact-messages'] as const;
const AD = ['ad-requests'] as const;

/**
 * عدّاد الشارة الموحّد — polling دوريّ (يتوقّف في التبويب الخلفي). مصدر شارة النافبار.
 * يُعطَّل لمن لا يملك أيّ صلاحية رؤية (تجنّب 403).
 */
export function useInboxUnread(enabled = true) {
  return useQuery({
    queryKey: [...INBOX, 'unread'],
    queryFn: () => inboxService.unreadCount(),
    refetchInterval: 5000,
    refetchIntervalInBackground: false,
    enabled,
  });
}

// ─── رسائل الاتصال ───────────────────────────────────────────────
export function useContactMessages(params: ContactListParams, enabled = true) {
  return useQuery({
    queryKey: [...CONTACT, 'list', params],
    queryFn: () => contactService.list(params),
    placeholderData: keepPreviousData,
    enabled,
  });
}

export function useContactMessage(id: number | null) {
  return useQuery({
    queryKey: [...CONTACT, 'detail', id],
    queryFn: () => contactService.show(id as number),
    enabled: id !== null,
  });
}

function useContactInvalidate() {
  const qc = useQueryClient();
  return () => {
    void qc.invalidateQueries({ queryKey: CONTACT });
    void qc.invalidateQueries({ queryKey: INBOX }); // الحالة قد تتغيّر ⇒ يتغيّر العدّاد
  };
}

/** Mark as Read (seen) — صامت (لا toast)؛ لا يغيّر الحالة/الشارة. */
export function useMarkContactRead() {
  const invalidate = useContactInvalidate();
  return useMutation({
    mutationFn: (id: number) => contactService.markRead(id),
    onSuccess: () => invalidate(),
  });
}

export function useUpdateContactStatus() {
  const invalidate = useContactInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; status: ContactStatusTarget }) =>
      contactService.updateStatus(v.id, v.status),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useReplyContact() {
  const invalidate = useContactInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; body: string }) => contactService.reply(v.id, v.body),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteContact() {
  const invalidate = useContactInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => contactService.remove(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

// ─── طلبات الإعلان ───────────────────────────────────────────────
export function useAdRequests(params: AdListParams, enabled = true) {
  return useQuery({
    queryKey: [...AD, 'list', params],
    queryFn: () => adRequestService.list(params),
    placeholderData: keepPreviousData,
    enabled,
  });
}

export function useAdRequest(id: number | null) {
  return useQuery({
    queryKey: [...AD, 'detail', id],
    queryFn: () => adRequestService.show(id as number),
    enabled: id !== null,
  });
}

function useAdInvalidate() {
  const qc = useQueryClient();
  return () => {
    void qc.invalidateQueries({ queryKey: AD });
    void qc.invalidateQueries({ queryKey: INBOX });
  };
}

export function useMarkAdRead() {
  const invalidate = useAdInvalidate();
  return useMutation({
    mutationFn: (id: number) => adRequestService.markRead(id),
    onSuccess: () => invalidate(),
  });
}

export function useUpdateAdStatus() {
  const invalidate = useAdInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; status: AdStatusTarget }) =>
      adRequestService.updateStatus(v.id, v.status),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useAddAdNote() {
  const invalidate = useAdInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; body: string }) => adRequestService.addNote(v.id, v.body),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteAd() {
  const invalidate = useAdInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => adRequestService.remove(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}
