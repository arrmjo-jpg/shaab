import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { PaginationMeta } from '@/types/users.types';
import type {
  AudienceItem,
  AudiencePreview,
  AudienceType,
  CampaignAction,
  CampaignData,
  CampaignsListParams,
  CampaignsListResult,
  CampaignSummary,
  ChannelHealthRow,
  ComposeCampaignPayload,
  EventChannelRow,
  EventMatrixRow,
  NotificationSettings,
  TemplateData,
  TemplatePayload,
  UpdateEventChannelPayload,
} from '@/types/notifications.types';

const BASE = '/admin/notifications';

function buildCampaignParams(p: CampaignsListParams): Record<string, string | number> {
  const params: Record<string, string | number> = { page: p.page, per_page: p.per_page };
  if (p.status) params['filter[status]'] = p.status;
  if (p.event_key) params['filter[event_key]'] = p.event_key;
  if (p.source) params['filter[source]'] = p.source;
  if (p.priority) params['filter[priority]'] = p.priority;
  if (p.sort) params.sort = p.sort;
  return params;
}

/**
 * مركز الإشعارات — كلّ المسارات تحت /admin/notifications. القراءة view؛ التأليف send؛
 * المصفوفة/القوالب/دورة الحياة/الإعدادات manage. الترقيم من meta.pagination (اتّفاق موحّد).
 */
export const notificationsService = {
  // ── Campaigns ──
  async listCampaigns(p: CampaignsListParams): Promise<CampaignsListResult> {
    const { data } = await http.get<ApiSuccess<CampaignData[]>>(`${BASE}/campaigns`, {
      params: buildCampaignParams(p),
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  async getCampaign(uuid: string): Promise<CampaignData> {
    const { data } = await http.get<ApiSuccess<CampaignData>>(`${BASE}/campaigns/${uuid}`);
    return data.data;
  },

  async summary(): Promise<CampaignSummary> {
    const { data } = await http.get<ApiSuccess<CampaignSummary>>(`${BASE}/campaigns/summary`);
    return data.data;
  },

  async compose(payload: ComposeCampaignPayload): Promise<CampaignData> {
    const { data } = await http.post<ApiSuccess<CampaignData>>(`${BASE}/campaigns`, payload);
    return data.data;
  },

  async lifecycle(uuid: string, action: CampaignAction): Promise<CampaignData> {
    const { data } = await http.post<ApiSuccess<CampaignData>>(`${BASE}/campaigns/${uuid}/${action}`);
    return data.data;
  },

  // ── Event Matrix ──
  async matrix(): Promise<EventMatrixRow[]> {
    const { data } = await http.get<ApiSuccess<EventMatrixRow[]>>(`${BASE}/matrix`);
    return data.data;
  },

  async updateChannel(id: number, payload: UpdateEventChannelPayload): Promise<EventChannelRow> {
    const { data } = await http.put<ApiSuccess<EventChannelRow>>(`${BASE}/matrix/channels/${id}`, payload);
    return data.data;
  },

  async toggleEvent(id: number): Promise<{ key: string; enabled: boolean }> {
    const { data } = await http.put<ApiSuccess<{ key: string; enabled: boolean }>>(
      `${BASE}/matrix/events/${id}/toggle`,
    );
    return data.data;
  },

  // ── Templates ──
  async listTemplates(filters?: { event_key?: string; channel?: string }): Promise<TemplateData[]> {
    const { data } = await http.get<ApiSuccess<TemplateData[]>>(`${BASE}/templates`, { params: filters });
    return data.data;
  },

  async variables(eventKey: string): Promise<string[]> {
    const { data } = await http.get<ApiSuccess<{ event_key: string; variables: string[] }>>(
      `${BASE}/templates/variables`,
      { params: { event_key: eventKey } },
    );
    return data.data.variables;
  },

  async createTemplate(payload: TemplatePayload): Promise<TemplateData> {
    const { data } = await http.post<ApiSuccess<TemplateData>>(`${BASE}/templates`, payload);
    return data.data;
  },

  async updateTemplate(id: number, payload: TemplatePayload): Promise<TemplateData> {
    const { data } = await http.put<ApiSuccess<TemplateData>>(`${BASE}/templates/${id}`, payload);
    return data.data;
  },

  async deleteTemplate(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`${BASE}/templates/${id}`);
    return data.message;
  },

  // ── Audiences ──
  async audiences(): Promise<AudienceItem[]> {
    const { data } = await http.get<ApiSuccess<AudienceItem[]>>(`${BASE}/audiences`);
    return data.data;
  },

  async previewAudience(audience: AudienceType): Promise<AudiencePreview> {
    const { data } = await http.get<ApiSuccess<AudiencePreview>>(`${BASE}/audiences/preview`, {
      params: { audience },
    });
    return data.data;
  },

  // ── Channel Health ──
  async health(): Promise<ChannelHealthRow[]> {
    const { data } = await http.get<ApiSuccess<ChannelHealthRow[]>>(`${BASE}/health`);
    return data.data;
  },

  async probe(): Promise<ChannelHealthRow[]> {
    const { data } = await http.post<ApiSuccess<ChannelHealthRow[]>>(`${BASE}/health/probe`);
    return data.data;
  },

  // ── Settings ──
  async getSettings(): Promise<NotificationSettings> {
    const { data } = await http.get<ApiSuccess<NotificationSettings>>(`${BASE}/settings`);
    return data.data;
  },

  async updateSettings(payload: NotificationSettings): Promise<NotificationSettings> {
    const { data } = await http.put<ApiSuccess<NotificationSettings>>(`${BASE}/settings`, payload);
    return data.data;
  },
};
