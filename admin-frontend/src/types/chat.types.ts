/** أنواع نطاق الشات الداخليّ — تطابق عقود الـ backend (Conversation/Message resources). */

export type ChatConversationType = 'general' | 'direct' | 'group';

export interface ChatParticipant {
  id: number;
  name: string;
  avatar: string | null;
}

export interface ChatLastMessage {
  id: number;
  body: string | null;
  sender_id: number | null;
  created_at: string | null;
}

export interface ChatConversation {
  id: number;
  uuid: string;
  type: ChatConversationType;
  title: string;
  participants: ChatParticipant[];
  last_message: ChatLastMessage | null;
  unread_count: number;
  last_message_at: string | null;
  created_at: string | null;
}

export interface ChatAttachment {
  id: number;
  url: string | null;
  thumb: string | null;
  is_image: boolean;
  mime: string;
  name: string;
}

export interface ChatMessage {
  id: number;
  uuid: string;
  conversation_id: number;
  deleted: boolean;
  body: string | null;
  mine: boolean;
  sender: ChatParticipant | null;
  attachment: ChatAttachment | null;
  edited_at: string | null;
  created_at: string | null;
}

export interface ChatMessagesResult {
  data: ChatMessage[];
  next_before: number | null;
}

export interface ChatContact {
  id: number;
  name: string;
  avatar: string | null;
}

export interface CreateConversationPayload {
  type: 'direct' | 'group';
  user_ids: number[];
  title?: string;
}

export interface SendMessagePayload {
  body?: string;
  attachment_asset_id?: number | null;
}
