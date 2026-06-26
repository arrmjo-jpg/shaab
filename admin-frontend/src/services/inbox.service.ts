import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { PaginationMeta } from '@/types/users.types';
import type {
  AdListParams,
  AdListResult,
  AdRequest,
  AdStatusTarget,
  ContactListParams,
  ContactListResult,
  ContactMessage,
  ContactStatusTarget,
  InboxUnreadCount,
} from '@/types/inbox.types';

/**
 * صندوق الوارد (الإدارة) — يستهلك نقاط contact-messages / ad-requests / inbox القائمة.
 * نفس عقد ApiResponse (data + meta.pagination). لا تغيير على الـ API.
 */

// ─── العدّاد الموحّد (مصدر الشارة) ───────────────────────────────
export const inboxService = {
  async unreadCount(): Promise<InboxUnreadCount> {
    const { data } = await http.get<ApiSuccess<InboxUnreadCount>>('/admin/inbox/unread-count');
    return data.data;
  },
};

// ─── رسائل الاتصال ───────────────────────────────────────────────
function contactParams(p: ContactListParams): Record<string, string | number> {
  const params: Record<string, string | number> = { page: p.page, per_page: p.per_page, dir: p.dir };
  if (p.status) params.status = p.status;
  if (p.type) params.type = p.type;
  if (p.q) params.q = p.q;
  return params;
}

export const contactService = {
  async list(p: ContactListParams): Promise<ContactListResult> {
    const { data } = await http.get<ApiSuccess<ContactMessage[]>>('/admin/contact-messages', {
      params: contactParams(p),
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async show(id: number): Promise<ContactMessage> {
    const { data } = await http.get<ApiSuccess<ContactMessage>>(`/admin/contact-messages/${id}`);
    return data.data;
  },

  async markRead(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/contact-messages/${id}/read`);
    return data.message;
  },

  async updateStatus(id: number, status: ContactStatusTarget): Promise<string> {
    const { data } = await http.patch<ApiSuccess<unknown>>(`/admin/contact-messages/${id}/status`, {
      status,
    });
    return data.message;
  },

  async reply(id: number, body: string): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/contact-messages/${id}/reply`, {
      body,
    });
    return data.message;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/contact-messages/${id}`);
    return data.message;
  },
};

// ─── طلبات الإعلان ───────────────────────────────────────────────
function adParams(p: AdListParams): Record<string, string | number> {
  const params: Record<string, string | number> = { page: p.page, per_page: p.per_page, dir: p.dir };
  if (p.status) params.status = p.status;
  if (p.q) params.q = p.q;
  return params;
}

export const adRequestService = {
  async list(p: AdListParams): Promise<AdListResult> {
    const { data } = await http.get<ApiSuccess<AdRequest[]>>('/admin/ad-requests', {
      params: adParams(p),
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async show(id: number): Promise<AdRequest> {
    const { data } = await http.get<ApiSuccess<AdRequest>>(`/admin/ad-requests/${id}`);
    return data.data;
  },

  async markRead(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/ad-requests/${id}/read`);
    return data.message;
  },

  async updateStatus(id: number, status: AdStatusTarget): Promise<string> {
    const { data } = await http.patch<ApiSuccess<unknown>>(`/admin/ad-requests/${id}/status`, {
      status,
    });
    return data.message;
  },

  async addNote(id: number, body: string): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/ad-requests/${id}/notes`, { body });
    return data.message;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/ad-requests/${id}`);
    return data.message;
  },

  /** تنزيل المرفق (صورة/ZIP) — blob عبر عميل http المُصادَق (Bearer). لا عرض/تنفيذ. */
  async downloadAttachment(id: number): Promise<Blob> {
    const { data } = await http.get(`/admin/ad-requests/${id}/attachment`, { responseType: 'blob' });
    return data as Blob;
  },
};
