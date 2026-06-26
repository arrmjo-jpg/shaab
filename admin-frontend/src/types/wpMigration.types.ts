/** أنواع منصّة ترحيل ووردبريس — تطابق عقود BackEnd (MigrationRunResource + WpSourceInspector::facts). */

export type MigrationRunStatus =
  | 'draft'
  | 'ready'
  | 'running'
  | 'paused'
  | 'stopping'
  | 'completed'
  | 'failed';

export type ConflictPolicy = 'prefer_news' | 'prefer_articles' | 'exclude';

export type WpCategoryMode = 'exclude' | 'news' | 'articles';

/** تصرّف المُشغِّل في تصنيف المصدر — مستقلّ عن نوع المحتوى. */
export type WpCategoryDisposition = 'create' | 'map' | 'exclude';

export interface WpSourceCategory {
  term_taxonomy_id: number;
  term_id: number;
  name: string;
  slug: string;
  parent: number;
  /** المنشورات المُسنَدة مباشرةً. */
  count: number;
  /** شامل الأبناء (مجموع الشجرة الفرعية) — للأب الهرمي. */
  total_count?: number;
}

export interface WpSourceFacts {
  scanned_at: string;
  prefix: string;
  site: { url: string | null; name: string | null; language: string | null };
  posts: { published: number; draft: number; pending: number; private: number; total: number };
  attachments: { total: number; by_mime: Array<{ mime: string; count: number }> };
  media: { featured_count: number; uploads_path?: string | null; uploads_readable?: boolean };
  categories: { count: number; items: WpSourceCategory[] };
  seo: {
    provider: string;
    yoast_indexable: boolean;
    primary_category_meta: number;
    focus_keywords: number;
  };
  authors: { guest_author_meta: number; wp_users: number };
  content: { gutenberg: number; with_inline_images: number; subtitle_meta: number };
  encoding?: {
    sampled: number;
    invalid_utf8: number;
    arabic_titles: number;
    suspected_mojibake: number;
    healthy: boolean;
  };
}

export interface ImpactPreviewSample {
  source: { id: number; title: string; excerpt: string };
  target: {
    type: 'news' | 'opinion' | 'conflict';
    is_conflict: boolean;
    target_categories: string[];
    byline: string;
    status: string;
    seo_title: string | null;
    seo_description: string | null;
  };
}

export interface ImpactPreview {
  generated_at: string;
  totals: { unique_posts: number; news: number; articles: number; conflicts: number };
  media: { featured_unique: number; posts_with_inline: number; deduped: boolean };
  seo: { mapped: number };
  redirects: { estimated: number };
  warnings: string[];
  samples: ImpactPreviewSample[];
}

export interface MigrationRunProgress {
  total: number;
  processed: number;
  done: number;
  partial: number;
  failed: number;
  skipped: number;
  media_imported: number;
  media_reused: number;
  media_failed: number;
}

export interface TimelineEvent {
  event: string;
  at: string;
}

export interface MigrationRun {
  id: number;
  name: string | null;
  status: MigrationRunStatus;
  conflict_policy: ConflictPolicy | null;
  connection: {
    db_host: string | null;
    db_port: number | null;
    db_name: string | null;
    db_username: string | null;
    table_prefix: string | null;
    uploads_path: string | null;
  };
  source_facts: WpSourceFacts | null;
  preview: ImpactPreview | null;
  preview_generated_at: string | null;
  mappings_updated_at: string | null;
  approved_at: string | null;
  preview_stale: boolean;
  approved: boolean;
  can_execute: boolean;
  /** ⚠️ TEMPORARY FEATURE — Quick Incremental Import — Remove before Production release. */
  quick_incremental_enabled: boolean;
  progress: MigrationRunProgress;
  timeline: TimelineEvent[];
  started_at: string | null;
  finished_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ConnectionTestResult {
  connected: boolean;
  read_ok: boolean;
  wordpress_detected: boolean;
  detected_prefix: string | null;
}

export interface ConnectionPayload {
  name?: string;
  db_host: string;
  db_port?: number;
  db_name: string;
  db_username: string;
  db_password?: string;
  table_prefix?: string;
  uploads_path?: string;
}

// ─── Category mapping (Step 3–4) ─────────────────────────────────────────────

/** صفّ تصنيف مصدر مدموج مع تنسيبه المحفوظ. */
export interface SourceCategoryRow {
  term_id: number;
  name: string;
  slug: string;
  parent: number;
  count: number;
  total_count: number;
  mode: WpCategoryMode;
  disposition: WpCategoryDisposition;
  target_category_id: number | null;
  created_category_id: number | null;
}

export interface TargetCategory {
  id: number;
  name: string;
  slug: string;
  scope: string;
  parent_id: number | null;
}

export interface TargetPools {
  locale: string;
  news: TargetCategory[];
  articles: TargetCategory[];
}

export interface CategoryMapInput {
  wp_term_id: number;
  wp_name: string;
  wp_slug?: string | null;
  wp_parent_id?: number | null;
  wp_count?: number;
  mode: WpCategoryMode;
  disposition: WpCategoryDisposition;
  target_category_id?: number | null;
}

export interface TaxonomyImportResult {
  created: number;
  reused: number;
  mapped: number;
  excluded: number;
}

// ─── Execution dashboard (Steps 7–9) ─────────────────────────────────────────

export type MigrationItemStatus =
  | 'pending'
  | 'queued'
  | 'processing'
  | 'partial'
  | 'done'
  | 'failed'
  | 'skipped';

export interface MigrationStatusCounts {
  total: number;
  pending: number;
  queued: number;
  processing: number;
  done: number;
  partial: number;
  failed: number;
  skipped: number;
}

export interface MigrationPerformance {
  elapsed_seconds: number;
  throughput_per_min: number;
  eta_seconds: number | null;
  percent: number;
}

export interface MigrationMediaMetrics {
  imported: number;
  reused: number;
  failed: number;
}

/** لقطة اللوحة الحيّة (GET /runs/{id}/stats). */
export interface MigrationStats {
  status: MigrationRunStatus;
  counts: MigrationStatusCounts;
  performance: MigrationPerformance;
  media: MigrationMediaMetrics;
  timeline: TimelineEvent[];
  started_at: string | null;
  finished_at: string | null;
}

export interface MigrationFailureBreakdown {
  reason: string;
  count: number;
}

/** ملخّص الختام (GET /runs/{id}/report). */
export interface MigrationReport {
  status: MigrationRunStatus;
  is_complete: boolean;
  counts: MigrationStatusCounts;
  succeeded: number;
  processed: number;
  success_rate: number;
  duration_seconds: number;
  media: MigrationMediaMetrics;
  failures: MigrationFailureBreakdown[];
  timeline: TimelineEvent[];
  started_at: string | null;
  finished_at: string | null;
}

/** صفّ عنصر دفتر للتنقيب في الفشل. */
export interface MigrationItemRow {
  id: number;
  wp_post_id: number;
  source_title: string | null;
  status: MigrationItemStatus;
  target_type: string | null;
  article_id: number | null;
  failure_reason: string | null;
  warnings: string[];
  attempts: number;
  last_step: string | null;
  last_error: string | null;
  media: MigrationMediaMetrics;
  checkpoints: {
    content_imported_at: string | null;
    media_imported_at: string | null;
    seo_imported_at: string | null;
    redirects_created_at: string | null;
  };
  created_at: string | null;
  updated_at: string | null;
}

export interface Pagination {
  total: number;
  count: number;
  per_page: number;
  current_page: number;
  total_pages: number;
}

export interface PaginatedItems {
  items: MigrationItemRow[];
  pagination: Pagination;
}

export type RetryMode = 'selected' | 'failed' | 'partial';

export interface RetryPayload {
  mode: RetryMode;
  ids?: number[];
}
