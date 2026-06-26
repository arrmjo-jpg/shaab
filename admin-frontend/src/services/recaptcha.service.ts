import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';

export interface RecaptchaConfig {
  enabled: boolean;
  version: string; // 'v2' | 'v3'
  site_key: string;
}

export const recaptchaService = {
  async config(): Promise<RecaptchaConfig> {
    const { data } = await http.get<ApiSuccess<RecaptchaConfig>>('/recaptcha/config');
    return data.data;
  },
};
