import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  CategoryMapInput,
  ConflictPolicy,
  ConnectionPayload,
  ConnectionTestResult,
  MigrationItemRow,
  MigrationItemStatus,
  MigrationReport,
  MigrationRun,
  MigrationStats,
  PaginatedItems,
  Pagination,
  RetryPayload,
  SourceCategoryRow,
  TargetPools,
  TaxonomyImportResult,
} from '@/types/wpMigration.types';

const BASE = '/admin/wp-migration';

export const wpMigrationService = {
  async listRuns(): Promise<MigrationRun[]> {
    const { data } = await http.get<ApiSuccess<MigrationRun[]>>(`${BASE}/runs`);
    return data.data;
  },

  async getRun(id: number): Promise<MigrationRun> {
    const { data } = await http.get<ApiSuccess<MigrationRun>>(`${BASE}/runs/${id}`);
    return data.data;
  },

  // قراءة فقط على المصدر — اختبار اتصال/قراءة + اكتشاف ووردبريس والبادئة.
  async testConnection(payload: ConnectionPayload): Promise<ConnectionTestResult> {
    const { data } = await http.post<ApiSuccess<ConnectionTestResult>>(
      `${BASE}/connection/test`,
      payload,
    );
    return data.data;
  },

  async createRun(payload: ConnectionPayload): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs`, payload);
    return data.data;
  },

  async audit(id: number): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs/${id}/audit`);
    return data.data;
  },

  async getCategories(runId: number): Promise<SourceCategoryRow[]> {
    const { data } = await http.get<ApiSuccess<{ items: SourceCategoryRow[] }>>(
      `${BASE}/runs/${runId}/categories`,
    );
    return data.data.items;
  },

  async getTargetCategories(runId: number): Promise<TargetPools> {
    const { data } = await http.get<ApiSuccess<TargetPools>>(`${BASE}/runs/${runId}/target-categories`);
    return data.data;
  },

  async saveCategoryMaps(
    runId: number,
    maps: CategoryMapInput[],
  ): Promise<{ count: number; included: number }> {
    const { data } = await http.put<ApiSuccess<{ count: number; included: number }>>(
      `${BASE}/runs/${runId}/category-maps`,
      { maps },
    );
    return data.data;
  },

  // Step 4.5: استيراد التصنيفات (إنشاء تصنيفات AlphaCMS من المصدر) — بعد التنسيب، قبل المعاينة.
  async importTaxonomy(runId: number): Promise<TaxonomyImportResult> {
    const { data } = await http.post<ApiSuccess<TaxonomyImportResult>>(
      `${BASE}/runs/${runId}/import-taxonomy`,
    );
    return data.data;
  },

  async generatePreview(runId: number): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs/${runId}/preview`);
    return data.data;
  },

  async approveRun(runId: number, conflictPolicy: ConflictPolicy): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs/${runId}/approve`, {
      conflict_policy: conflictPolicy,
    });
    return data.data;
  },

  // ─── Execution (Steps 6–9) ──────────────────────────────────────────────────

  async start(runId: number, incremental = false): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs/${runId}/start`, {
      incremental,
    });
    return data.data;
  },

  // ⚠️ TEMPORARY FEATURE — Quick Incremental Import — Remove before Production release.
  // TODO(production): احذف هذه الدالّة عند إزالة الاختصار.
  async quickIncremental(runId: number): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs/${runId}/quick-incremental`);
    return data.data;
  },

  async pause(runId: number): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs/${runId}/pause`);
    return data.data;
  },

  async resume(runId: number): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs/${runId}/resume`);
    return data.data;
  },

  async stop(runId: number): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs/${runId}/stop`);
    return data.data;
  },

  async getStats(runId: number): Promise<MigrationStats> {
    const { data } = await http.get<ApiSuccess<MigrationStats>>(`${BASE}/runs/${runId}/stats`);
    return data.data;
  },

  async getReport(runId: number): Promise<MigrationReport> {
    const { data } = await http.get<ApiSuccess<MigrationReport>>(`${BASE}/runs/${runId}/report`);
    return data.data;
  },

  async getItems(
    runId: number,
    params: { status?: MigrationItemStatus; page?: number; per_page?: number } = {},
  ): Promise<PaginatedItems> {
    const { data } = await http.get<ApiSuccess<MigrationItemRow[]>>(`${BASE}/runs/${runId}/items`, {
      params,
    });
    return {
      items: data.data,
      pagination: data.meta.pagination as Pagination,
    };
  },

  async retry(runId: number, payload: RetryPayload): Promise<MigrationRun> {
    const { data } = await http.post<ApiSuccess<MigrationRun>>(`${BASE}/runs/${runId}/retry`, payload);
    return data.data;
  },
};
