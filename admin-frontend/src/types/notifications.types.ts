import type { PaginationMeta } from '@/types/users.types';

// ─── Enums (مرايا backend) ──────────────────────────────────────────────
export type CampaignStatus =
  | 'draft'
  | 'scheduled'
  | 'queued'
  | 'sending'
  | 'paused'
  | 'completed'
  | 'partially_completed'
  | 'failed'
  | 'cancelled';

export type ChannelKey = 'firebase' | 'whatsapp' | 'email';
export type DeliveryMode = 'automatic' | 'manual_approval' | 'disabled';
export type ChannelHealthState = 'healthy' | 'degraded' | 'disabled' | 'unconfigured';
export type CampaignAction = 'approve' | 'pause' | 'resume' | 'cancel';

export type AudienceType =
  | 'all'
  | 'logged'
  | 'guests'
  | 'sports_followers'
  | 'whatsapp_subscribers'
  | 'email_subscribers'
  | 'android'
  | 'ios';

// ─── Campaigns ──────────────────────────────────────────────────────────
export interface CampaignStats {
  channels: number;
  targeted: number;
  sent: number;
  failed: number;
  skipped: number;
  invalid: number;
}

export interface CampaignChannel {
  id: number;
  channel: ChannelKey;
  status: string;
  mode: DeliveryMode | null;
  addressing: string | null;
  tracking_mode: string | null;
  channel_priority: number;
  fallback_channel: string | null;
  template_id: number | null;
  skip_reason: string | null;
  topic: string | null;
  content: Record<string, unknown> | null;
  counters: {
    targeted: number;
    sent: number;
    delivered: number;
    failed: number;
    skipped: number;
    invalid: number;
  };
}

export interface CampaignData {
  uuid: string;
  event_key: string;
  event_label: string;
  source: string | null;
  trigger_type: string | null;
  priority: string | null;
  title: string | null;
  status: CampaignStatus;
  status_label: string;
  is_terminal: boolean;
  allowed_transitions: CampaignStatus[];
  audience: Record<string, unknown> | null;
  scheduled_at: string | null;
  started_at: string | null;
  finished_at: string | null;
  stats: CampaignStats;
  channels?: CampaignChannel[];
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface CampaignsListParams {
  page: number;
  per_page: number;
  status?: string;
  event_key?: string;
  source?: string;
  priority?: string;
  sort?: string;
}

export interface CampaignsListResult {
  data: CampaignData[];
  pagination: PaginationMeta;
}

export interface ComposeCampaignPayload {
  event_key: string;
  title: string;
  body?: string;
  image_url?: string;
  deep_link_type?: string;
  deep_link_value?: string;
  audience: AudienceType;
  audience_params?: Record<string, unknown>;
  channels?: ChannelKey[];
  scheduled_at?: string | null;
  requires_approval?: boolean;
  locale?: string;
  idempotency_key?: string;
  variables?: Record<string, string>;
}

export interface CampaignSummary {
  by_status: Record<string, number>;
  totals: { sent: number; failed: number; skipped: number; invalid: number };
}

// ─── Event Matrix ───────────────────────────────────────────────────────
export interface EventChannelRow {
  id: number;
  channel: ChannelKey;
  mode: DeliveryMode;
  channel_priority: number;
  fallback_channel: string | null;
  template_id: number | null;
  default_audience_id: number | null;
}

export interface EventMatrixRow {
  id: number;
  key: string;
  label: string;
  category: string | null;
  source: string | null;
  default_priority: string | null;
  enabled: boolean;
  archived: boolean;
  user_visible: boolean;
  manual_dispatch: boolean;
  variables: string[];
  channels: EventChannelRow[];
}

export interface UpdateEventChannelPayload {
  mode: DeliveryMode;
  channel_priority: number;
  fallback_channel?: string | null;
  template_id?: number | null;
  default_audience_id?: number | null;
}

// ─── Templates ──────────────────────────────────────────────────────────
export interface TemplateData {
  id: number;
  event_key: string;
  event_label: string;
  channel: ChannelKey;
  locale: string | null;
  title: string | null;
  body: string | null;
  image_strategy: string | null;
  deep_link_type: string | null;
  deep_link_value: string | null;
  is_default: boolean;
  available_variables: string[];
  created_at: string | null;
  updated_at: string | null;
}

export interface TemplatePayload {
  event_key: string;
  channel: ChannelKey;
  locale?: string;
  title?: string;
  body?: string;
  image_strategy?: string;
  deep_link_type?: string;
  deep_link_value?: string;
  variables?: string[];
  is_default?: boolean;
}

// ─── Audiences ──────────────────────────────────────────────────────────
export interface AudienceSpec {
  type: AudienceType;
  params: unknown[];
  topic: string | null;
  estimated_count: number | null;
}

export interface AudienceItem {
  key: AudienceType;
  spec: AudienceSpec;
}

export interface AudiencePreview {
  audience: AudienceType;
  users: number;
  devices: number;
}

// ─── Channel Health ─────────────────────────────────────────────────────
export interface ChannelHealthRow {
  channel: ChannelKey;
  effective_state: ChannelHealthState | null;
  sendable: boolean;
  configured: boolean;
  healthy: boolean;
  last_checked_at: string | null;
  last_ok_at: string | null;
  last_error: string | null;
  latency_ms: number | null;
  consecutive_failures: number | null;
}

// ─── Settings ───────────────────────────────────────────────────────────
export interface NotificationSettings {
  enabled: boolean;
  critical_bypass: boolean;
  quiet_hours_enabled: boolean;
  quiet_hours_start: string;
  quiet_hours_end: string;
  quiet_hours_timezone: string;
}
