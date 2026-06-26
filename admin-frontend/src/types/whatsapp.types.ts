// أنواع نطاق حملات واتساب — مجموعات + جهات اتصال (المرحلة 3).

export interface WhatsappGroupData {
  id: number;
  name: string;
  description: string | null;
  is_default: boolean;
  contacts_count: number;
  created_at: string | null;
}

export interface WhatsappGroupUpsertPayload {
  name: string;
  description: string | null;
}

export type WhatsappContactStatus = 'subscribed' | 'unsubscribed';

export interface WhatsappContactData {
  id: number;
  name: string;
  phone: string;
  status: WhatsappContactStatus;
  source: string;
  groups: { id: number; name: string }[];
  created_at: string | null;
}

export interface WhatsappContactUpsertPayload {
  name: string;
  phone: string;
  groups: number[];
}

export interface WhatsappContactsListParams {
  page: number;
  per_page: number;
  q: string;
  group_id: number | '';
  status: '' | WhatsappContactStatus;
}

export interface WhatsappPagination {
  total: number;
  count: number;
  per_page: number;
  current_page: number;
  total_pages: number;
}

export interface WhatsappImportPayload {
  file: File;
  group_id: number;
  duplicates: 'update' | 'skip';
}

export interface WhatsappImportReport {
  total: number;
  created: number;
  updated: number;
  skipped: number;
  failed: number;
  errors: { row: number; value?: string; reason: string }[];
}

// ─── الحملات ──────────────────────────────────────────────
export type WhatsappCampaignType = 'promo' | 'article';
export type WhatsappCampaignStatus =
  | 'draft'
  | 'scheduled'
  | 'sending'
  | 'completed'
  | 'failed'
  | 'cancelled';
export type WhatsappMediaType = 'none' | 'image' | 'video';

export interface WhatsappCampaignData {
  id: number;
  uuid: string;
  name: string;
  type: WhatsappCampaignType;
  status: WhatsappCampaignStatus;
  message_text: string | null;
  media_type: WhatsappMediaType;
  media_asset_id: number | null;
  article_id: number | null;
  recipients_total: number;
  sent_count: number;
  failed_count: number;
  scheduled_at: string | null;
  started_at: string | null;
  finished_at: string | null;
  created_at: string | null;
  groups: { id: number; name: string }[];
}

export interface WhatsappCampaignCreatePayload {
  name: string;
  type: WhatsappCampaignType;
  groups: number[];
  scheduled_at?: string | null;
  message_text?: string | null;
  media_type?: WhatsappMediaType;
  media_asset_id?: number | null;
  article_id?: number | null;
}

export interface WhatsappCampaignPreview {
  text: string;
  media_type: WhatsappMediaType;
  media_url: string | null;
  recipients: number;
}

export interface WhatsappCampaignMessageData {
  id: number;
  phone: string;
  status: 'pending' | 'sent' | 'failed';
  error: string | null;
  sent_at: string | null;
}

export interface WhatsappCampaignsListParams {
  page: number;
  per_page: number;
  type: '' | WhatsappCampaignType;
  status: '' | WhatsappCampaignStatus;
}
