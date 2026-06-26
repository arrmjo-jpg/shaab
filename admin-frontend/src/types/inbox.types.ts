import type { PaginationMeta } from '@/types/users.types';

/**
 * أنواع صندوق الوارد (الإدارة) — تطابق عقود الـ backend حرفياً:
 * ContactMessageResource / AdRequestResource + meta.pagination + InboxUnreadCountAction.
 */

// ─── رسائل الاتصال ───────────────────────────────────────────────
export type ContactMessageType = 'inquiry' | 'complaint' | 'suggestion' | 'other';
export type ContactMessageStatus = 'new' | 'in_review' | 'replied' | 'closed';

/** الأهداف المسموحة لتغيير الحالة يدويّاً (UpdateContactMessageStatusRequest). */
export type ContactStatusTarget = 'in_review' | 'closed';

export interface ContactMessage {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  subject: string;
  type: ContactMessageType;
  message: string;
  status: ContactMessageStatus;
  is_read: boolean;
  read_at: string | null;
  reply_body: string | null;
  replied_at: string | null;
  replied_by: string | null;
  created_at: string | null;
}

export interface ContactListParams {
  page: number;
  per_page: number;
  status: '' | ContactMessageStatus;
  type: '' | ContactMessageType;
  q: string;
  /** اتّجاه الفرز على created_at (الباك إند يفترض created_at افتراضاً). */
  dir: 'desc' | 'asc';
}

export interface ContactListResult {
  data: ContactMessage[];
  pagination: PaginationMeta;
}

// ─── طلبات الإعلان ───────────────────────────────────────────────
export type AdRequestStatus =
  | 'new'
  | 'contacted'
  | 'negotiating'
  | 'completed'
  | 'rejected'
  | 'closed';

/** الأهداف المسموحة لتغيير الحالة (UpdateAdRequestStatusRequest) — كل البايبلاين عدا «new». */
export type AdStatusTarget = 'contacted' | 'negotiating' | 'completed' | 'rejected' | 'closed';

export interface AdRequestNote {
  id: number;
  body: string;
  author: string | null;
  created_at: string | null;
}

export interface AdRequest {
  id: number;
  company_name: string;
  contact_name: string;
  email: string;
  phone: string;
  website: string | null;
  /** image | html (مقيَّد عبر AdType في الباك إند؛ string لأمان البيانات القديمة). */
  ad_type: string;
  description: string;
  /** المرفق (صورة/ZIP) — لا يُكشَف المسار الخامّ؛ التنزيل عبر نقطة محميّة بالـid. */
  has_attachment: boolean;
  attachment_name: string | null;
  status: AdRequestStatus;
  is_read: boolean;
  read_at: string | null;
  reviewed_by: string | null;
  reviewed_at: string | null;
  /** يُحمَّل في endpoint التفاصيل (show) فقط. */
  notes?: AdRequestNote[];
  created_at: string | null;
}

export interface AdListParams {
  page: number;
  per_page: number;
  status: '' | AdRequestStatus;
  q: string;
  /** اتّجاه الفرز على created_at. */
  dir: 'desc' | 'asc';
}

export interface AdListResult {
  data: AdRequest[];
  pagination: PaginationMeta;
}

// ─── شارة العدّاد الموحّد ─────────────────────────────────────────
export interface InboxUnreadCount {
  contact_count: number;
  ad_count: number;
  total: number;
}
