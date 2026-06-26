/** أنواع نظام الإعلانات — تطابق عقود الـ backend (AdZoneResource / AdCampaignResource).
 *  تعيد استخدام عقد ApiResponse + pagination meta. لا بنية موازية. */

import type { PaginationMeta } from '@/types/users.types';

// ─── Enums (مرايا تعدادات الـ backend) ───────────────────────────────────────
export type AdPlacementType = 'banner' | 'inline' | 'sidebar' | 'interstitial' | 'overlay' | 'preroll';
export type AdSelectorStrategy = 'weighted' | 'round_robin' | 'even';
export type AdCampaignStatus = 'draft' | 'scheduled' | 'active' | 'paused' | 'completed' | 'archived';
export type AdPacingMode = 'none' | 'even' | 'asap';

export const AD_PLACEMENT_TYPES: AdPlacementType[] = ['banner', 'inline', 'sidebar', 'interstitial', 'overlay', 'preroll'];
export const AD_SELECTOR_STRATEGIES: AdSelectorStrategy[] = ['weighted', 'round_robin', 'even'];
export const AD_CAMPAIGN_STATUSES: AdCampaignStatus[] = ['draft', 'scheduled', 'active', 'paused', 'completed', 'archived'];
export const AD_PACING_MODES: AdPacingMode[] = ['none', 'even', 'asap'];

/** آلة حالة دورة الحياة — مرآة AdCampaignLifecycle::manualTargets (الانتقالات اليدوية فقط). */
export const AD_CAMPAIGN_TRANSITIONS: Record<AdCampaignStatus, AdCampaignStatus[]> = {
  draft: ['scheduled', 'archived'],
  scheduled: ['active', 'draft', 'archived'],
  active: ['paused', 'completed', 'archived'],
  paused: ['active', 'completed', 'archived'],
  completed: ['paused', 'archived'],
  archived: ['draft', 'paused'],
};

// ─── Ad Zones ────────────────────────────────────────────────────────────────
export interface AdZoneData {
  id: number;
  key: string;
  name: string;
  description: string | null;
  placement_type: AdPlacementType | null;
  selector_strategy: AdSelectorStrategy | null;
  width: number | null;
  height: number | null;
  locale: string | null;
  is_active: boolean;
  sort_order: number;
  placements_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface AdZoneUpsertPayload {
  key: string;
  name: string;
  description?: string | null;
  placement_type: AdPlacementType;
  selector_strategy?: AdSelectorStrategy;
  width?: number | null;
  height?: number | null;
  locale?: string | null;
  is_active?: boolean;
  sort_order?: number;
}

// ─── Campaigns ───────────────────────────────────────────────────────────────
export interface AdCampaignData {
  id: number;
  uuid: string;
  name: string;
  advertiser_name: string | null;
  status: AdCampaignStatus | null;
  priority: number;
  weight: number;
  starts_at: string | null;
  ends_at: string | null;
  /** decimal:2 → نصّ من الـ API (أو null). */
  budget_total: string | null;
  budget_spent: string | null;
  pacing_mode: AdPacingMode | null;
  targeting: Record<string, unknown> | null;
  creatives_count?: number;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
}

export interface AdCampaignUpsertPayload {
  name: string;
  advertiser_name?: string | null;
  priority?: number;
  weight?: number;
  starts_at?: string | null;
  ends_at?: string | null;
  budget_total?: number | null;
  pacing_mode?: AdPacingMode;
}

export type AdTrashedFilter = '' | 'only' | 'with';

export interface AdCampaignsListParams {
  page: number;
  per_page: number;
  search: string;
  status: '' | AdCampaignStatus;
  pacing_mode: '' | AdPacingMode;
  sort: string;
  trashed: AdTrashedFilter;
}

export interface AdCampaignsListResult {
  data: AdCampaignData[];
  pagination: PaginationMeta;
}

// ─── Creatives ─────────────────────────────────────────────────────────────
export type AdCreativeType = 'image' | 'html' | 'video';

/** أنواع الإبداع القابلة للاختيار في الإدارة (video مؤجَّل في هذه المرحلة). */
export const AD_CREATIVE_TYPES_SELECTABLE: AdCreativeType[] = ['image', 'html'];

export interface AdCreativeMedia {
  id: number;
  url: string | null;
}

export interface AdCreativeData {
  id: number;
  uuid: string;
  ad_campaign_id: number;
  type: AdCreativeType | null;
  title: string;
  alt_text: string | null;
  landing_url: string | null;
  html_code: string | null;
  media_asset_id: number | null;
  media?: AdCreativeMedia | null;
  weight: number;
  is_active: boolean;
  campaign?: { id: number; name: string; status: AdCampaignStatus | null };
  placements_count?: number;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
}

export interface AdCreativeUpsertPayload {
  ad_campaign_id?: number;
  type?: AdCreativeType;
  title: string;
  alt_text?: string | null;
  landing_url?: string | null;
  html_code?: string | null;
  media_asset_id?: number | null;
  weight?: number;
  is_active?: boolean;
}

export interface AdCreativesListParams {
  page: number;
  per_page: number;
  search: string;
  ad_campaign_id: string;
  type: '' | AdCreativeType;
  is_active: '' | '0' | '1';
  sort: string;
  trashed: AdTrashedFilter;
}

export interface AdCreativesListResult {
  data: AdCreativeData[];
  pagination: PaginationMeta;
}

// ─── Placements ────────────────────────────────────────────────────────────
export type AdDeviceClass = 'desktop' | 'mobile' | 'tablet';
export const AD_DEVICE_CLASSES: AdDeviceClass[] = ['desktop', 'mobile', 'tablet'];

/** مرآة AdPlacementCompatibility — أنواع الإبداع المسموح بها لكل نوع مساحة. */
export const AD_PLACEMENT_COMPAT: Record<AdPlacementType, AdCreativeType[]> = {
  banner: ['image', 'html'],
  inline: ['image', 'html'],
  sidebar: ['image', 'html'],
  interstitial: ['image', 'html'],
  overlay: ['image', 'html'],
  preroll: ['video'],
};

export function isPlacementCompatible(zoneType: AdPlacementType, creativeType: AdCreativeType): boolean {
  return AD_PLACEMENT_COMPAT[zoneType]?.includes(creativeType) ?? false;
}

export interface AdPlacementData {
  id: number;
  ad_creative_id: number;
  ad_zone_id: number;
  weight: number | null;
  effective_weight: number;
  is_active: boolean;
  device_targets: string[] | null;
  creative?: { id: number; title: string; type: AdCreativeType | null };
  zone?: { id: number; key: string; name: string; placement_type: AdPlacementType | null };
  created_at: string | null;
  updated_at: string | null;
}

export interface AdPlacementAttachPayload {
  ad_creative_id: number;
  ad_zone_id: number;
  weight?: number | null;
  is_active?: boolean;
  device_targets?: string[] | null;
}

export interface AdPlacementUpdatePayload {
  weight?: number | null;
  is_active?: boolean;
  device_targets?: string[] | null;
}

export interface AdPlacementsListParams {
  page: number;
  per_page: number;
  ad_zone_id: string;
  ad_creative_id: string;
  is_active: '' | '0' | '1';
  sort: string;
}

export interface AdPlacementsListResult {
  data: AdPlacementData[];
  pagination: PaginationMeta;
}

// ─── Analytics ───────────────────────────────────────────────────────────────
export interface AdAnalyticsWindow {
  range: string;
  from: string;
  to: string;
  days: number;
}

export interface AdAnalyticsTrendPoint {
  date: string;
  impressions: number;
  clicks: number;
}

export interface AdAnalyticsTopRow {
  id: number;
  name: string;
  impressions: number;
  clicks: number;
  ctr: number;
}

export interface AdAnalyticsChannels {
  direct: number;
  internal: number;
  search: number;
  social: number;
  referral: number;
}

export interface AdAnalyticsData {
  window: AdAnalyticsWindow;
  totals: { impressions: number; clicks: number; ctr: number };
  trend: { points: AdAnalyticsTrendPoint[] };
  channels: AdAnalyticsChannels;
  top_campaigns: AdAnalyticsTopRow[];
  top_creatives: AdAnalyticsTopRow[];
  top_zones: AdAnalyticsTopRow[];
}
