import type { PaginationMeta } from './users.types';

export type WriterRequestStatus = 'pending' | 'approved' | 'rejected';

export interface WriterRequestData {
  id: number;
  status: WriterRequestStatus;
  status_label: string;
  note: string | null;
  reviewed_at: string | null;
  created_at: string;
  user: { id: number; name: string; email: string };
  reviewer?: { id: number; name: string } | null;
}

export interface WriterRequestsListParams {
  page: number;
  per_page: number;
  search: string;
  status: '' | WriterRequestStatus;
}

export interface WriterRequestsListResult {
  data: WriterRequestData[];
  pagination: PaginationMeta;
}
