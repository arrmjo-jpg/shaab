/** أنواع تحليلات الكيان (فيديو/بثّ) — تطابق عقود BackEnd (VideoEntityAnalyticsAction،
 *  BroadcastEntityAnalyticsAction). تيليمتري إلى-الأمام للسلاسل الزمنية؛ بعض المقاييس
 *  مؤجّلة بصدق (available=false) عند غياب التتبّع. لا بنية موازية — نفس عقد ApiResponse. */

export type AnalyticsRangeKey = '24h' | '7d' | '30d' | 'custom';

export interface AnalyticsWindow {
  range: string;
  from: string;
  to: string;
  days: number;
}

export interface AnalyticsTrendPoint {
  date: string;
  views: number;
  likes: number;
  dislikes: number;
  favorites: number;
}

export interface AnalyticsTrend {
  window: AnalyticsWindow;
  forward_only: boolean;
  points: AnalyticsTrendPoint[];
  totals: { views: number; likes: number; dislikes: number; favorites: number };
}

export interface TrafficChannels {
  direct: number;
  internal: number;
  search: number;
  social: number;
  referral: number;
}

/** مقياس مؤجّل بصدق (لا تيليمتري بعد) — يُعرَض كحالة «غير متعقَّب». */
export interface DeferredMetric {
  available: false;
  reason: string;
}

// ─── Video entity analytics ──────────────────────────────────────────────────

export interface VideoEntityAnalytics {
  entity: {
    id: number;
    title: string;
    slug: string;
    locale: string;
    status: string;
    visibility: string;
    is_featured: boolean;
    duration_seconds: number;
    created_at: string | null;
  };
  engagement: {
    views: number;
    likes: number;
    dislikes: number;
    favorites: number;
    unique_reactors: number;
    engagement_rate: number;
  };
  trend: AnalyticsTrend;
  traffic: { forward_only: boolean; total: number; channels: TrafficChannels };
  distribution: {
    is_featured: boolean;
    category: { id: number; name: string; slug: string } | null;
    playlists: Array<{ id: number; title: string; slug: string }>;
    linked_vods: Array<{ id: number; title: string; slug: string; kind: string; status: string }>;
  };
  seo: {
    slug: string;
    locale: string;
    canonical_path: string;
    redirect_history: Array<{ old_path: string; locale: string; reason: string | null; at: string | null }>;
  };
  publishing: {
    status: string;
    visibility: string;
    published_at: string | null;
    is_scheduled: boolean;
    created_at: string | null;
    timeline: Array<{
      event: string;
      at: string;
      changes: Array<{ field: string; from: unknown; to: unknown }>;
    }>;
  };
  watch: DeferredMetric;
}

// ─── Broadcast entity analytics ──────────────────────────────────────────────

export interface BroadcastConcurrencyPoint {
  at: string | null;
  viewers: number;
}

export interface BroadcastHealthEvent {
  status: string;
  latency_ms: number | null;
  reason: string | null;
  at: string | null;
}

export interface BroadcastModerationEvent {
  event: string;
  description: string;
  member: string | null;
  reason: string | null;
  at: string | null;
}

export interface BroadcastEntityAnalytics {
  entity: {
    id: number;
    title: string;
    slug: string;
    kind: string;
    status: string;
    is_featured: boolean;
  };
  live_performance: {
    current_viewers: number;
    peak_all_time: number;
    peak_in_window: number;
    average_concurrent: number;
    sample_count: number;
    viewer_count_snapshot: number;
    unique_viewers: DeferredMetric;
  };
  engagement: { likes: number; dislikes: number; favorites: number };
  concurrency: {
    window: AnalyticsWindow;
    forward_only: boolean;
    note: string;
    points: BroadcastConcurrencyPoint[];
  };
  engagement_trend: AnalyticsTrend;
  timeline: {
    scheduled_at: string | null;
    started_at: string | null;
    ended_at: string | null;
    is_live: boolean;
    start_delay_seconds: number | null;
    duration_seconds: number | null;
  };
  health: {
    window: AnalyticsWindow;
    retention_days: number;
    last_status: string | null;
    last_message: string | null;
    last_checked_at: string | null;
    consecutive_failures: number;
    failure_count: number;
    healthy_count: number;
    check_count: number;
    recovery_count: number;
    avg_latency_ms: number | null;
    max_latency_ms: number | null;
    recent_events: BroadcastHealthEvent[];
  };
  moderation: {
    kicks: number;
    bans: number;
    unbans: number;
    closures: number;
    reopens: number;
    emergency_shutdowns: number;
    recent_events: BroadcastModerationEvent[];
  };
  notifications: {
    reminder_subscribers: number;
    global_subscribers: number;
    live_notified_at: string | null;
    reminder_dispatched_at: string | null;
    delivery: DeferredMetric;
  };
}

// ─── Reel entity analytics (v1 — current data only) ──────────────────────────

/** كتلة مؤجّلة بصدق مع أسماء المقاييس غير المتعقَّبة بعد (تيليمتري قصير-الشكل). */
export interface DeferredGroup {
  available: false;
  reason: string;
  metrics: string[];
}

export interface ReelEntityAnalytics {
  entity: {
    id: number;
    title: string;
    slug: string;
    locale: string;
    status: string;
    is_featured: boolean;
    duration_seconds: number;
    published_at: string | null;
    created_at: string | null;
  };
  engagement: {
    views: number;
    likes: number;
    dislikes: number;
    favorites: number;
    unique_reactors: number;
    engagement_rate: number;
  };
  trend: AnalyticsTrend;
  traffic: { forward_only: boolean; total: number; channels: TrafficChannels };
  performance: {
    trending_score: number;
    velocity_per_day: number;
    momentum_pct: number | null;
    baseline: { published_reels: number; avg_views: number; vs_baseline_pct: number | null };
  };
  publishing: {
    status: string;
    is_featured: boolean;
    published_at: string | null;
    is_scheduled: boolean;
    days_since_publish: number | null;
    locale: string;
    translations: Array<{ id: number; locale: string; title: string; slug: string }>;
  };
  deferred: {
    watch: DeferredGroup;
    discovery: DeferredGroup;
  };
}

// ─── Reel fleet analytics (v1 — cross-reel aggregates) ───────────────────────

export interface ReelFleetAnalytics {
  engagement: { views: number; likes: number; dislikes: number; favorites: number };
  top_performers: Array<{
    id: number;
    title: string;
    slug: string;
    locale: string;
    is_featured: boolean;
    views: number;
    score: number;
  }>;
  publish_time: Array<{ hour: number; reels: number; avg_views: number }>;
  language: Array<{ locale: string; reels: number; views: number }>;
  featured_impact: {
    featured: { reels: number; avg_views: number };
    regular: { reels: number; avg_views: number };
    lift_pct: number | null;
  };
}

// ─── Article entity analytics (v1 — current data only) ───────────────────────

export interface ArticleEntityAnalytics {
  entity: {
    id: number;
    title: string;
    slug: string;
    locale: string;
    type: string;
    status: string;
    is_featured: boolean;
    published_at: string | null;
    created_at: string | null;
  };
  engagement: {
    views: number;
    likes: number;
    dislikes: number;
    favorites: number;
    unique_reactors: number;
    engagement_rate: number;
  };
  trend: AnalyticsTrend;
  traffic: { forward_only: boolean; total: number; channels: TrafficChannels };
  performance: {
    trending_score: number;
    velocity_per_day: number;
    momentum_pct: number | null;
    baseline: { published_articles: number; avg_views: number; vs_baseline_pct: number | null };
  };
  publishing: {
    status: string;
    is_featured: boolean;
    published_at: string | null;
    is_scheduled: boolean;
    days_since_publish: number | null;
    locale: string;
    translations: Array<{ id: number; locale: string; title: string; slug: string }>;
  };
}

// ─── Article fleet analytics (v1 — cross-article aggregates) ─────────────────

export interface ArticleFleetAnalytics {
  engagement: { views: number; likes: number; dislikes: number; favorites: number };
  top_performers: Array<{
    id: number;
    title: string;
    slug: string;
    locale: string;
    is_featured: boolean;
    views: number;
    score: number;
  }>;
  publish_time: Array<{ hour: number; articles: number; avg_views: number }>;
  language: Array<{ locale: string; articles: number; views: number }>;
  featured_impact: {
    featured: { articles: number; avg_views: number };
    regular: { articles: number; avg_views: number };
    lift_pct: number | null;
  };
}

// ─── Site analytics dashboard (v1 — unified, read-only) ──────────────────────

export interface SiteTopItem {
  id: number;
  title: string;
  views: number;
  score: number;
}

export interface SiteAnalytics {
  engagement: { views: number; likes: number; favorites: number };
  inventory: {
    articles: number;
    reels: number;
    videos: number;
    broadcasts: number;
    polls: number;
    epapers: number;
  };
  ads: { impressions: number; clicks: number };
  polls: { votes: number };
  trend: Array<{ date: string; views: number }>;
  // Phase B — قد تغيب من حمولة v1 مكاشة قديمة حتى انتهاء TTL (≤5د) ⇒ اختياريّة + عرض مشروط.
  top?: {
    articles: SiteTopItem[];
    news: SiteTopItem[];
    reels: SiteTopItem[];
    videos: SiteTopItem[];
  };
  channels?: { direct: number; internal: number; search: number; social: number; referral: number };
}
