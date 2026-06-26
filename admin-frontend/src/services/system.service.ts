import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  ClearCacheResult,
  FailedJobsPage,
  FailedJobsQuery,
  ManageFailedJobsPayload,
  OpsOverview,
  SystemDiagnostics,
} from '@/types/system.types';

const BASE = '/admin/system';

export const systemService = {
  async opsOverview(): Promise<OpsOverview> {
    const { data } = await http.get<ApiSuccess<OpsOverview>>(`${BASE}/ops-overview`);
    return data.data;
  },

  async failedJobs(params: FailedJobsQuery): Promise<FailedJobsPage> {
    const { data } = await http.get<ApiSuccess<FailedJobsPage>>(`${BASE}/failed-jobs`, { params });
    return data.data;
  },

  async retryFailedJobs(payload: ManageFailedJobsPayload): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`${BASE}/failed-jobs/retry`, payload);
    return data.message;
  },

  async deleteFailedJobs(payload: ManageFailedJobsPayload): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`${BASE}/failed-jobs/delete`, payload);
    return data.message;
  },

  // تشخيص تشغيلي آمن (قراءة) — يتطلّب scheduler.view.
  async diagnostics(): Promise<SystemDiagnostics> {
    const { data } = await http.get<ApiSuccess<SystemDiagnostics>>(`${BASE}/diagnostics`);
    return data.data;
  },

  // تفريغ كاش المحتوى العام (استرداد تشغيلي) — يتطلّب cache.clear.
  async clearContentCache(): Promise<ClearCacheResult> {
    const { data } = await http.post<ApiSuccess<ClearCacheResult>>(`${BASE}/cache/clear`);
    return data.data;
  },
};
