import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { chatService } from '@/services/chat.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type { CreateConversationPayload, SendMessagePayload } from '@/types/chat.types';

const CHAT = ['chat'] as const;

function useChatInvalidate() {
  const qc = useQueryClient();
  return () => void qc.invalidateQueries({ queryKey: CHAT });
}

/**
 * قائمة المحادثات — تُستطلَع دورياً (polling) حتى وصول Reverb (Slice 3). مصدر شارة
 * غير المقروء في النافبار أيضاً.
 */
export function useConversations(enabled = true) {
  return useQuery({
    queryKey: [...CHAT, 'conversations'],
    queryFn: () => chatService.conversations(),
    refetchInterval: 5000,
    refetchIntervalInBackground: false, // يتوقّف في التبويب الخلفي (تقليل الحمل)
    enabled,
  });
}

/** رسائل محادثة — polling أسرع قليلاً عند فتح الخيط. */
export function useChatMessages(conversationId: number | null) {
  return useQuery({
    queryKey: [...CHAT, 'messages', conversationId],
    queryFn: () => chatService.messages(conversationId as number),
    enabled: conversationId !== null, // الخيط المفتوح فقط يُستطلَع
    refetchInterval: 4000,
    refetchIntervalInBackground: false, // يتوقّف في التبويب الخلفي
  });
}

export function useChatContacts(search: string, enabled: boolean) {
  return useQuery({
    queryKey: [...CHAT, 'contacts', search],
    queryFn: () => chatService.contacts(search),
    enabled,
  });
}

export function useCreateConversation() {
  const invalidate = useChatInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: CreateConversationPayload) => chatService.create(payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useSendMessage() {
  const invalidate = useChatInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { conversationId: number; payload: SendMessagePayload }) =>
      chatService.send(v.conversationId, v.payload),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useMarkRead() {
  const invalidate = useChatInvalidate();
  return useMutation({
    mutationFn: (conversationId: number) => chatService.markRead(conversationId),
    onSuccess: () => invalidate(),
  });
}

export function useUpdateMessage() {
  const invalidate = useChatInvalidate();
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { id: number; body: string }) => chatService.updateMessage(v.id, v.body),
    onSuccess: () => invalidate(),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useDeleteMessage() {
  const invalidate = useChatInvalidate();
  const { success, error } = useToast();
  return useMutation({
    mutationFn: (id: number) => chatService.deleteMessage(id),
    onSuccess: (m) => {
      success(m);
      invalidate();
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}
