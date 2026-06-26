import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  WhatsappCampaignCreatePayload,
  WhatsappCampaignData,
  WhatsappCampaignMessageData,
  WhatsappCampaignPreview,
  WhatsappCampaignsListParams,
  WhatsappContactData,
  WhatsappContactsListParams,
  WhatsappContactUpsertPayload,
  WhatsappGroupData,
  WhatsappGroupUpsertPayload,
  WhatsappImportPayload,
  WhatsappImportReport,
  WhatsappPagination,
} from '@/types/whatsapp.types';

/** مجموعات + جهات اتصال واتساب — نفس عقد بقية الخدمات (ApiSuccess + meta.pagination). */
export const whatsappService = {
  // ─── المجموعات ──────────────────────────────────────────────
  async listGroups(): Promise<WhatsappGroupData[]> {
    const { data } = await http.get<ApiSuccess<WhatsappGroupData[]>>('/admin/whatsapp-groups');
    return data.data;
  },

  async createGroup(payload: WhatsappGroupUpsertPayload): Promise<WhatsappGroupData> {
    const { data } = await http.post<ApiSuccess<WhatsappGroupData>>('/admin/whatsapp-groups', payload);
    return data.data;
  },

  async updateGroup(id: number, payload: WhatsappGroupUpsertPayload): Promise<WhatsappGroupData> {
    const { data } = await http.put<ApiSuccess<WhatsappGroupData>>(`/admin/whatsapp-groups/${id}`, payload);
    return data.data;
  },

  async removeGroup(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/whatsapp-groups/${id}`);
    return data.message;
  },

  // ─── جهات الاتصال ──────────────────────────────────────────
  async listContacts(
    params: WhatsappContactsListParams,
  ): Promise<{ data: WhatsappContactData[]; pagination: WhatsappPagination | null }> {
    const query: Record<string, string | number> = {
      page: params.page,
      per_page: params.per_page,
    };
    if (params.q.trim() !== '') query.q = params.q.trim();
    if (params.group_id !== '') query.group_id = params.group_id;
    if (params.status !== '') query.status = params.status;

    const { data } = await http.get<ApiSuccess<WhatsappContactData[]> & {
      meta?: { pagination?: WhatsappPagination };
    }>('/admin/whatsapp-contacts', { params: query });

    return { data: data.data, pagination: data.meta?.pagination ?? null };
  },

  async createContact(payload: WhatsappContactUpsertPayload): Promise<WhatsappContactData> {
    const { data } = await http.post<ApiSuccess<WhatsappContactData>>('/admin/whatsapp-contacts', payload);
    return data.data;
  },

  async updateContact(id: number, payload: WhatsappContactUpsertPayload): Promise<WhatsappContactData> {
    const { data } = await http.put<ApiSuccess<WhatsappContactData>>(`/admin/whatsapp-contacts/${id}`, payload);
    return data.data;
  },

  async removeContact(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/whatsapp-contacts/${id}`);
    return data.message;
  },

  async importContacts(payload: WhatsappImportPayload): Promise<WhatsappImportReport> {
    const form = new FormData();
    form.append('file', payload.file);
    form.append('group_id', String(payload.group_id));
    form.append('duplicates', payload.duplicates);
    const { data } = await http.post<ApiSuccess<WhatsappImportReport>>(
      '/admin/whatsapp-contacts/import',
      form,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    );
    return data.data;
  },

  /** تصدير — تنزيل ملف (blob) باحترام الفلاتر الحالية. */
  async exportContacts(
    params: { q: string; group_id: number | ''; status: '' | 'subscribed' | 'unsubscribed' },
    format: 'csv' | 'xlsx',
  ): Promise<Blob> {
    const query: Record<string, string | number> = { format };
    if (params.q.trim() !== '') query.q = params.q.trim();
    if (params.group_id !== '') query.group_id = params.group_id;
    if (params.status !== '') query.status = params.status;
    const { data } = await http.get('/admin/whatsapp-contacts/export', {
      params: query,
      responseType: 'blob',
    });
    return data as Blob;
  },

  // ─── الحملات ──────────────────────────────────────────────
  async listCampaigns(
    params: WhatsappCampaignsListParams,
  ): Promise<{ data: WhatsappCampaignData[]; pagination: WhatsappPagination | null }> {
    const query: Record<string, string | number> = { page: params.page, per_page: params.per_page };
    if (params.type !== '') query.type = params.type;
    if (params.status !== '') query.status = params.status;
    const { data } = await http.get<ApiSuccess<WhatsappCampaignData[]> & {
      meta?: { pagination?: WhatsappPagination };
    }>('/admin/whatsapp-campaigns', { params: query });
    return { data: data.data, pagination: data.meta?.pagination ?? null };
  },

  async getCampaign(id: number): Promise<WhatsappCampaignData> {
    const { data } = await http.get<ApiSuccess<WhatsappCampaignData>>(`/admin/whatsapp-campaigns/${id}`);
    return data.data;
  },

  async createCampaign(payload: WhatsappCampaignCreatePayload): Promise<WhatsappCampaignData> {
    const { data } = await http.post<ApiSuccess<WhatsappCampaignData>>('/admin/whatsapp-campaigns', payload);
    return data.data;
  },

  async removeCampaign(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/whatsapp-campaigns/${id}`);
    return data.message;
  },

  async recipientsCount(groups: number[]): Promise<number> {
    const { data } = await http.post<ApiSuccess<{ recipients: number }>>(
      '/admin/whatsapp-campaigns/recipients-count',
      { groups },
    );
    return data.data.recipients;
  },

  async previewCampaign(id: number): Promise<WhatsappCampaignPreview> {
    const { data } = await http.get<ApiSuccess<WhatsappCampaignPreview>>(
      `/admin/whatsapp-campaigns/${id}/preview`,
    );
    return data.data;
  },

  async sendCampaign(id: number): Promise<WhatsappCampaignData> {
    const { data } = await http.post<ApiSuccess<WhatsappCampaignData>>(`/admin/whatsapp-campaigns/${id}/send`);
    return data.data;
  },

  async cancelCampaign(id: number): Promise<WhatsappCampaignData> {
    const { data } = await http.post<ApiSuccess<WhatsappCampaignData>>(`/admin/whatsapp-campaigns/${id}/cancel`);
    return data.data;
  },

  async testCampaign(id: number, phone: string): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/whatsapp-campaigns/${id}/test`, { phone });
    return data.message;
  },

  async campaignMessages(
    id: number,
    params: { page: number; per_page: number; status: '' | 'pending' | 'sent' | 'failed' },
  ): Promise<{ data: WhatsappCampaignMessageData[]; pagination: WhatsappPagination | null }> {
    const query: Record<string, string | number> = { page: params.page, per_page: params.per_page };
    if (params.status !== '') query.status = params.status;
    const { data } = await http.get<ApiSuccess<WhatsappCampaignMessageData[]> & {
      meta?: { pagination?: WhatsappPagination };
    }>(`/admin/whatsapp-campaigns/${id}/messages`, { params: query });
    return { data: data.data, pagination: data.meta?.pagination ?? null };
  },
};
