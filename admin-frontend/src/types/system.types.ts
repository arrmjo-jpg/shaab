// أنواع وحدة العمليات (System Operations): لوحة الرصد + المهام الفاشلة.

export interface OpsOverview {
  queue: {
    pending: number;
    failed: number;
  };
  media: {
    sync_pending: number;
    sync_syncing: number;
    sync_failed: number;
    sync_synced: number;
    unsynced: number;
    stuck_transcoding: number;
    failed_transcode_24h: number;
    failed_mirror: number;
  };
  remote_healthy: boolean | null;
  scheduler: {
    tasks: number;
    failed_last_run: number;
    last_run_at: string | null;
  };
}

export interface FailedJob {
  id: string;
  connection: string | null;
  queue: string | null;
  name: string;
  max_tries: number | null;
  exception: string;
  failed_at: string | null;
}

export interface FailedJobsPage {
  data: FailedJob[];
  meta: {
    total: number;
    page: number;
    per_page: number;
    last_page: number;
  };
}

export interface FailedJobsQuery {
  q?: string;
  page?: number;
  per_page?: number;
}

/** حمولة إدارة المهام الفاشلة: معرّفات محدّدة أو الكلّ. */
export interface ManageFailedJobsPayload {
  ids?: string[];
  all?: boolean;
}

/** تشخيص تشغيلي آمن — حقائق وقت التشغيل دون أي أسرار. */
export interface SystemDiagnostics {
  app: {
    environment: string;
    laravel_version: string;
    php_version: string;
    debug: boolean;
    locale: string;
    timezone: string;
    url: string;
  };
  maintenance: { down: boolean };
  drivers: {
    cache: string;
    queue: string;
    session: string;
    database: string;
    mail: string;
  };
  cache: { supports_tagging: boolean };
  connectivity: { database: boolean; cache: boolean };
  queue: { pending: number; failed: number };
  scheduler: { tasks: number; last_run_at: string | null };
  opcache: boolean;
  checked_at: string;
}

/** نتيجة تفريغ كاش المحتوى العام. */
export interface ClearCacheResult {
  cleared: string[];
  at: string;
}
