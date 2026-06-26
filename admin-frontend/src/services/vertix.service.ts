import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';

export type VertixPhaseState = 'idle' | 'running' | 'completed' | 'failed';

export interface VertixPhaseStatus {
  status: VertixPhaseState;
  source_total: number;
  imported: number;
  remaining: number;
  failed: number;
  watermark?: number;
}

export interface VertixError {
  type: string;
  id: number;
  error: string | null;
  at: string | null;
}

export interface VertixStatus {
  connected: boolean;
  categories: VertixPhaseStatus;
  news: VertixPhaseStatus;
  errors: VertixError[];
}

const BASE = '/admin/vertix-migration';

export const vertixService = {
  async status(): Promise<VertixStatus> {
    const { data } = await http.get<ApiSuccess<VertixStatus>>(`${BASE}/status`);
    return data.data;
  },

  async importCategories(): Promise<VertixStatus> {
    const { data } = await http.post<ApiSuccess<VertixStatus>>(`${BASE}/import-categories`);
    return data.data;
  },

  async importNews(): Promise<VertixStatus> {
    const { data } = await http.post<ApiSuccess<VertixStatus>>(`${BASE}/import-news`);
    return data.data;
  },

  async stopNews(): Promise<VertixStatus> {
    const { data } = await http.post<ApiSuccess<VertixStatus>>(`${BASE}/stop-news`);
    return data.data;
  },
};
