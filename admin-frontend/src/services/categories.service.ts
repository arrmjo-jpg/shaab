import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  CategoryBulkPayload,
  CategoryData,
  CategoryUpsertPayload,
  TrashedCategory,
} from '@/types/content.types';

/**
 * Normalize tree payloads to guarantee `children: CategoryData[]` on every node.
 *
 * The backend `CategoryResource` uses `whenLoaded('children')` which omits the
 * key entirely on leaf nodes (Laravel resource MissingValue is dropped from the
 * JSON output). Consumers walking the tree must never receive `undefined` for
 * `children` — guarantee it here so every downstream component can assume an
 * iterable array.
 */
function normalizeTree(nodes: unknown): CategoryData[] {
  if (!Array.isArray(nodes)) return [];
  return nodes.map((n) => {
    const node = (n ?? {}) as Partial<CategoryData>;
    return {
      ...(node as CategoryData),
      children: normalizeTree((node as { children?: unknown }).children),
    };
  });
}

export const categoriesService = {
  async list(): Promise<CategoryData[]> {
    const { data } = await http.get<ApiSuccess<CategoryData[]>>('/admin/categories');
    return normalizeTree(data.data);
  },

  async get(id: number): Promise<CategoryData> {
    const { data } = await http.get<ApiSuccess<CategoryData>>(`/admin/categories/${id}`);
    return { ...data.data, children: normalizeTree((data.data as { children?: unknown }).children) };
  },

  async create(payload: CategoryUpsertPayload): Promise<string> {
    const { data } = await http.post<ApiSuccess<CategoryData>>('/admin/categories', payload);
    return data.message;
  },

  async update(id: number, payload: CategoryUpsertPayload): Promise<string> {
    const { data } = await http.put<ApiSuccess<CategoryData>>(`/admin/categories/${id}`, payload);
    return data.message;
  },

  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/categories/${id}`);
    return data.message;
  },

  /** Reorder a category among its siblings (up/down). */
  async move(id: number, direction: 'up' | 'down'): Promise<string> {
    const { data } = await http.patch<ApiSuccess<CategoryData>>(
      `/admin/categories/${id}/move`,
      { direction },
    );
    return data.message;
  },

  /** Apply status/visibility changes to many categories at once. */
  async bulkUpdate(ids: number[], payload: CategoryBulkPayload): Promise<string> {
    const { data } = await http.patch<ApiSuccess<{ updated: number }>>('/admin/categories/bulk', {
      ids,
      ...payload,
    });
    return data.message;
  },

  /** Soft-deleted categories (flat list). */
  async listTrashed(): Promise<TrashedCategory[]> {
    const { data } = await http.get<ApiSuccess<TrashedCategory[]>>('/admin/categories/trashed');
    return data.data;
  },

  /** Restore a soft-deleted category. */
  async restore(id: number): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(`/admin/categories/${id}/restore`);
    return data.message;
  },

  /** Permanently delete a category (irreversible). */
  async forceDelete(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/categories/${id}/force`);
    return data.message;
  },
};
