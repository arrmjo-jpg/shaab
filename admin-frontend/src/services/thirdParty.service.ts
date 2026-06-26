import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { ThirdPartyData, ThirdPartyUpdatePayload } from '@/types/thirdParty.types';

export const thirdPartyService = {
  async get(): Promise<ThirdPartyData> {
    const { data } = await http.get<ApiSuccess<ThirdPartyData>>('/admin/settings/third_party');
    return data.data;
  },

  async update(payload: ThirdPartyUpdatePayload): Promise<string> {
    const { data } = await http.put<ApiSuccess<unknown>>('/admin/settings/third_party', payload);
    return data.message;
  },

  async uploadFirebase(file: File): Promise<string> {
    const form = new FormData();
    form.append('service_account', file);
    // لا تضبط Content-Type يدوياً — يجب أن يولّد المتصفح boundary لـ multipart
    const { data } = await http.post<ApiSuccess<unknown>>(
      '/admin/settings/third_party/firebase-credentials',
      form,
    );
    return data.message;
  },

  async testSportmonks(): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      '/admin/settings/third-party/test/sportmonks',
    );
    return data.message;
  },

  async testOpenweather(): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      '/admin/settings/third-party/test/openweather',
    );
    return data.message;
  },

  async testWhatsapp(): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      '/admin/settings/third-party/test/whatsapp',
    );
    return data.message;
  },
};
