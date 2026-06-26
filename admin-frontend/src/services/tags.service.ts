import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type {
  ContentLocale,
  ManagedTag,
  TagsListParams,
  TagsListResult,
  TagUpdatePayload,
} from '@/types/content.types';
import type { PaginationMeta } from '@/types/users.types';

export interface TagSuggestion {
  id: number;
  name: string;
  slug: string;
}

export const tagsService = {
  async list(locale: ContentLocale, q: string, limit = 20): Promise<TagSuggestion[]> {
    const { data } = await http.get<ApiSuccess<TagSuggestion[]>>('/admin/tags', {
      params: { locale, q, limit },
    });
    return data.data;
  },

  /** Tags management — paginated list with real usage counts. */
  async listManaged(p: TagsListParams): Promise<TagsListResult> {
    const { data } = await http.get<ApiSuccess<ManagedTag[]>>('/admin/tags/manage', {
      params: { page: p.page, per_page: p.per_page, q: p.q, locale: p.locale },
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },

  /** Rename a tag (per-locale name). */
  async update(id: number, payload: TagUpdatePayload): Promise<ManagedTag> {
    const { data } = await http.put<ApiSuccess<ManagedTag>>(`/admin/tags/${id}`, payload);
    return data.data;
  },

  /** Delete a tag (detaches it from all content). */
  async remove(id: number): Promise<string> {
    const { data } = await http.delete<ApiSuccess<unknown>>(`/admin/tags/${id}`);
    return data.message;
  },
};
