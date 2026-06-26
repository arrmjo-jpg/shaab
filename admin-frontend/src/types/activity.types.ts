import type { PaginationMeta } from './users.types';

export interface ActivityItem {
  id: number;
  log_name: string | null;
  event: string | null;
  description: string | null;
  subject_type: string | null;
  subject_id: number | null;
  causer: { id: number; name: string } | null;
  changes: {
    attributes?: Record<string, unknown>;
    old?: Record<string, unknown>;
  };
  context: Record<string, unknown>;
  created_at: string | null;
}

export interface ActivityListParams {
  page: number;
  per_page: number;
  log_name: string;
  event: string;
  from: string;
  to: string;
}

export interface ActivityListResult {
  data: ActivityItem[];
  pagination: PaginationMeta;
}
