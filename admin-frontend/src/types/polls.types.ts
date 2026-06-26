/** أنواع نظام الاستطلاعات — تطابق عقود الـ backend (PollResource).
 *  تعيد استخدام عقد ApiResponse + pagination meta. لا بنية موازية. */

import type { AnalyticsWindow } from '@/types/analytics.types';
import type { PaginationMeta } from '@/types/users.types';

// ─── Enums (مرايا تعدادات الـ backend) ───────────────────────────────────────
export type PollState = 'inactive' | 'scheduled' | 'open' | 'closed';
export type PollAudienceMode = 'public' | 'authenticated';
export type PollResultVisibility = 'always' | 'after_vote' | 'after_close';

export const POLL_AUDIENCE_MODES: PollAudienceMode[] = ['public', 'authenticated'];
export const POLL_RESULT_VISIBILITIES: PollResultVisibility[] = ['always', 'after_vote', 'after_close'];

// ─── Options ─────────────────────────────────────────────────────────────────
export interface PollOptionData {
  id: number;
  label: string;
  sort_order: number;
  votes_count: number;
}

export interface PollOptionUpsertPayload {
  id?: number;
  label: string;
  sort_order?: number;
}

// ─── Polls ─────────────────────────────────────────────────────────────────
export interface PollData {
  id: number;
  uuid: string;
  question: string;
  allow_multiple: boolean;
  is_active: boolean;
  state: PollState;
  starts_at: string | null;
  ends_at: string | null;
  audience_mode: PollAudienceMode;
  result_visibility: PollResultVisibility;
  options_count?: number;
  options?: PollOptionData[];
  total_votes?: number;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface PollUpsertPayload {
  question: string;
  allow_multiple?: boolean;
  starts_at?: string | null;
  ends_at?: string | null;
  audience_mode?: PollAudienceMode;
  result_visibility?: PollResultVisibility;
  options: PollOptionUpsertPayload[];
  // ملاحظة: is_active لا يُرسَل — التفعيل إجراء نشر مستقلّ (PATCH /active).
}

export type PollTrashedFilter = '' | 'only' | 'with';

export interface PollsListParams {
  page: number;
  per_page: number;
  search: string;
  is_active: '' | '0' | '1';
  sort: string;
  trashed: PollTrashedFilter;
}

export interface PollsListResult {
  data: PollData[];
  pagination: PaginationMeta;
}

// ─── Analytics ───────────────────────────────────────────────────────────────
/** تحليلات الاستطلاعات — تطابق عقود الـ backend (PollFleetAnalyticsAction،
 *  PollEntityAnalyticsAction). لا بيانات زيارات/قنوات ولا «إلى-الأمام»؛ سلسلة
 *  المشاركة الزمنية كاملة ضمن النافذة. المصوّتون الفريدون رقم دقيق (لا تقريب). */

export interface PollParticipationPoint {
  date: string;
  votes: number;
}

// ─── Poll fleet analytics (cross-poll aggregates) ────────────────────────────

export interface PollFleetAnalytics {
  kpis: {
    total_polls: number;
    active_polls: number;
    open_polls: number;
    total_votes: number;
    total_selections: number;
  };
  status_breakdown: {
    open: number;
    scheduled: number;
    closed: number;
    inactive: number;
  };
  top_polls: Array<{
    id: number;
    uuid: string;
    question: string;
    state: PollState;
    unique_voters: number;
  }>;
  recent_participation: {
    days: number;
    points: PollParticipationPoint[];
    totals: { votes: number };
  };
}

// ─── Poll entity analytics (contextual, ranged) ──────────────────────────────

export interface PollEntityAnalytics {
  entity: {
    id: number;
    uuid: string;
    question: string;
    state: PollState;
    is_active: boolean;
    allow_multiple: boolean;
    audience_mode: PollAudienceMode;
    result_visibility: PollResultVisibility;
    starts_at: string | null;
    ends_at: string | null;
    created_at: string | null;
  };
  participation: {
    unique_voters: number;
    total_selections: number;
    avg_selections_per_voter: number;
    options_count: number;
  };
  distribution: Array<{
    id: number;
    label: string;
    sort_order: number;
    votes: number;
    percentage: number;
  }>;
  trend: {
    window: AnalyticsWindow;
    points: PollParticipationPoint[];
    totals: { votes: number };
  };
}
