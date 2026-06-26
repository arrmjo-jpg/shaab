/** أنواع نطاق مركز عمليات البثّ — تطابق عقود الـ backend (BroadcastResource،
 *  BroadcastCategoryResource، BroadcastDashboardAction، طلبات الإنشاء/التحديث/الإشراف/دورة الحياة).
 *  لا بنية موازية — نفس عقد الـ API (ApiResponse + pagination meta) ونمط بقية الخدمات. */
import type { PaginationMeta } from '@/types/users.types';

export type BroadcastStatus =
  | 'draft'
  | 'scheduled'
  | 'live'
  | 'offline'
  | 'ended'
  | 'failed'
  | 'archived';
export type BroadcastKind = 'live' | 'tv' | 'radio';
export type BroadcastSourceType =
  | 'hls'
  | 'iptv'
  | 'youtube_live'
  | 'external_provider'
  | 'icecast'
  | 'shoutcast';

export interface BroadcastCategoryRef {
  id: number;
  name: string;
  slug: string;
}

export interface BroadcastVodRef {
  id: number;
  title: string;
  slug: string;
}

export interface BroadcastSeo {
  title: string | null;
  description: string | null;
  keywords: string | null;
  canonical_url: string | null;
  robots: string | null;
}

export interface BroadcastHealth {
  status: string | null;
  checked_at: string | null;
  message: string | null;
}

export interface BroadcastData {
  id: number;
  uuid: string;
  status: BroadcastStatus;
  kind: BroadcastKind;
  source_type: BroadcastSourceType;
  title: string;
  slug: string;
  excerpt: string | null;
  description: string | null;
  source_url: string;
  category_id: number | null;
  category?: BroadcastCategoryRef | null;
  vod_video_id: number | null;
  vod?: BroadcastVodRef | null;
  thumbnail_path: string | null;
  poster_path: string | null;
  cover_media_id: number | null;
  cover_url?: string | null;
  seo: BroadcastSeo;
  scheduled_at: string | null;
  started_at: string | null;
  ended_at: string | null;
  health: BroadcastHealth;
  viewer_count: number;
  sort_order: number;
  is_featured: boolean;
  is_public: boolean;
  meta: unknown;
  creator?: { id: number | null; name: string | null };
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

/** StoreBroadcastRequest / UpdateBroadcastRequest — على الإنشاء يلزم
 *  title + kind + source_type + source_url؛ على التحديث كل الحقول اختيارية.
 *  الحالة (status) لا تُضبط هنا أبداً — تبدأ مسودّة، والانتقالات عبر دورة الحياة. */
export interface BroadcastUpsertPayload {
  title?: string;
  kind?: BroadcastKind;
  source_type?: BroadcastSourceType;
  source_url?: string;
  category_id?: number | null;
  vod_video_id?: number | null;
  excerpt?: string | null;
  description?: string | null;
  slug?: string | null;
  thumbnail_path?: string | null;
  poster_path?: string | null;
  cover_media_id?: number | null;
  scheduled_at?: string | null;
  is_featured?: boolean;
  is_public?: boolean;
  sort_order?: number;
  seo_title?: string | null;
  seo_description?: string | null;
  seo_keywords?: string | null;
  canonical_url?: string | null;
  robots?: string | null;
}

export interface BroadcastsListParams {
  page: number;
  per_page: number;
  search: string;
  status: '' | BroadcastStatus;
  kind: '' | BroadcastKind;
  source_type: '' | BroadcastSourceType;
  category_id: '' | number;
  is_featured: '' | '0' | '1';
  is_public: '' | '0' | '1';
  sort: '' | '-created_at' | 'title' | '-scheduled_at' | '-started_at' | 'sort_order';
  trashed?: '' | 'only' | 'with';
}

export interface BroadcastsListResult {
  data: BroadcastData[];
  pagination: PaginationMeta;
}

// ─── Lifecycle (BroadcastLifecycleController) ──────────────────────────────

/** انتقالات دورة الحياة المسموحة عبر POST /{id}/{action}. */
export type BroadcastLifecycleAction =
  | 'schedule'
  | 'start'
  | 'offline'
  | 'resume'
  | 'end'
  | 'fail'
  | 'archive';

/** أجسام انتقالات دورة الحياة — schedule يتطلّب scheduled_at؛ fail يقبل reason اختياري. */
export interface BroadcastLifecycleBody {
  scheduled_at?: string;
  reason?: string;
}

// ─── Moderation (BroadcastModerationController) ────────────────────────────

export type BroadcastModerationAction =
  | 'kick'
  | 'ban'
  | 'unban'
  | 'close'
  | 'reopen'
  | 'emergency-shutdown';

/** هدف الإشراف: user_id (قويّ) أو member (أفضل-جهد) — أحدهما حصراً. */
export interface BroadcastModerationBody {
  user_id?: number;
  member?: string;
  duration_minutes?: number;
  reason?: string;
}

// ─── Dashboard (BroadcastDashboardAction) ──────────────────────────────────

export interface BroadcastDashboardLive {
  id: number;
  title: string;
  slug: string;
  kind: BroadcastKind;
  is_featured: boolean;
  viewer_count: number;
  started_at: string | null;
  health: BroadcastHealth;
  audience_closed: boolean;
}

export interface BroadcastDashboardScheduled {
  id: number;
  title: string;
  kind: BroadcastKind;
  scheduled_at: string;
  reminder_subscribers: number;
  reminder_dispatched: boolean;
}

export interface BroadcastChannelOverview {
  live: number;
  offline: number;
  failed: number;
  total: number;
}

export interface BroadcastHealthAlert {
  id: number;
  title: string;
  kind: BroadcastKind;
  status: string | null;
  message: string | null;
  checked_at: string | null;
}

export interface BroadcastDashboardData {
  status_counts: Record<BroadcastStatus, number>;
  live: BroadcastDashboardLive[];
  scheduled_today: BroadcastDashboardScheduled[];
  channels: {
    tv: BroadcastChannelOverview;
    radio: BroadcastChannelOverview;
  };
  health_alerts: BroadcastHealthAlert[];
  audience: { closed: Array<{ id: number; title: string }> };
  notifications: {
    global_subscribers: number;
    upcoming_with_reminders: number;
  };
  totals: {
    live_viewers: number;
    live: number;
    scheduled: number;
    failed: number;
  };
}

// ─── Broadcast categories (flat) ───────────────────────────────────────────

export interface BroadcastCategoryData {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  cover_media_id: number | null;
  cover_url?: string | null;
  is_active: boolean;
  sort_order: number;
  seo: { title: string | null; description: string | null };
  broadcasts_count?: number;
  deleted_at: string | null;
  created_at: string;
}

export interface BroadcastCategoryUpsertPayload {
  name?: string;
  slug?: string | null;
  description?: string | null;
  cover_media_id?: number | null;
  is_active?: boolean;
  sort_order?: number;
  seo_title?: string | null;
  seo_description?: string | null;
}
