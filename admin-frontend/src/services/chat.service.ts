import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  ChatContact,
  ChatConversation,
  ChatMessage,
  ChatMessagesResult,
  CreateConversationPayload,
  SendMessagePayload,
} from '@/types/chat.types';

/**
 * الشات الداخليّ — REST مصدر الحقيقة (Reverb طبقة نقل لاحقاً). نفس عقد ApiResponse.
 */
export const chatService = {
  async conversations(): Promise<ChatConversation[]> {
    const { data } = await http.get<ApiSuccess<ChatConversation[]>>('/admin/chat/conversations');
    return data.data;
  },

  async contacts(search = ''): Promise<ChatContact[]> {
    const { data } = await http.get<ApiSuccess<ChatContact[]>>('/admin/chat/contacts', {
      params: search ? { search } : {},
    });
    return data.data;
  },

  async create(payload: CreateConversationPayload): Promise<ChatConversation> {
    const { data } = await http.post<ApiSuccess<ChatConversation>>('/admin/chat/conversations', payload);
    return data.data;
  },

  async messages(conversationId: number, before?: number | null): Promise<ChatMessagesResult> {
    const { data } = await http.get<ApiSuccess<ChatMessage[]>>(
      `/admin/chat/conversations/${conversationId}/messages`,
      { params: before ? { before } : {} },
    );
    const meta = data.meta as { next_before: number | null };
    return { data: data.data, next_before: meta?.next_before ?? null };
  },

  async send(conversationId: number, payload: SendMessagePayload): Promise<ChatMessage> {
    const { data } = await http.post<ApiSuccess<ChatMessage>>(
      `/admin/chat/conversations/${conversationId}/messages`,
      payload,
    );
    return data.data;
  },

  async markRead(conversationId: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      `/admin/chat/conversations/${conversationId}/read`,
    );
    return data.message;
  },

  async updateMessage(id: number, body: string): Promise<ChatMessage> {
    const { data } = await http.patch<ApiSuccess<ChatMessage>>(`/admin/chat/messages/${id}`, { body });
    return data.data;
  },

  async deleteMessage(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/chat/messages/${id}`);
    return data.message;
  },
};
