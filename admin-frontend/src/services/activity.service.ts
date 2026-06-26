import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { PaginationMeta } from '@/types/users.types';
import type {
  ActivityItem,
  ActivityListParams,
  ActivityListResult,
} from '@/types/activity.types';

export const activityService = {
  async list(p: ActivityListParams): Promise<ActivityListResult> {
    const params: Record<string, string | number> = {
      page: p.page,
      per_page: p.per_page,
    };
    if (p.log_name) params['filter[log_name]'] = p.log_name;
    if (p.event) params['filter[event]'] = p.event;
    if (p.from) params['filter[from]'] = p.from;
    if (p.to) params['filter[to]'] = p.to;

    const { data } = await http.get<ApiSuccess<ActivityItem[]>>('/admin/activity', {
      params,
    });
    const pagination = (data.meta as { pagination: PaginationMeta }).pagination;
    return { data: data.data, pagination };
  },
};
