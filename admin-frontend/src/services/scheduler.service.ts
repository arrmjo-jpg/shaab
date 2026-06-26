import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { ScheduledTask, UpdateScheduledTaskPayload } from '@/types/scheduler.types';

const BASE = '/admin/system/scheduler';

export const schedulerService = {
  async list(): Promise<ScheduledTask[]> {
    const { data } = await http.get<ApiSuccess<ScheduledTask[]>>(BASE);
    return data.data;
  },

  async update(key: string, payload: UpdateScheduledTaskPayload): Promise<ScheduledTask> {
    const { data } = await http.patch<ApiSuccess<ScheduledTask>>(`${BASE}/${key}`, payload);
    return data.data;
  },

  async run(key: string): Promise<ScheduledTask> {
    const { data } = await http.post<ApiSuccess<ScheduledTask>>(`${BASE}/${key}/run`);
    return data.data;
  },
};
