import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { AdminUser, AuthTokenPayload } from '@/types/auth.types';

function withCaptcha<T extends object>(body: T, token?: string): T & { recaptcha_token?: string } {
  return token ? { ...body, recaptcha_token: token } : body;
}

export const authService = {
  async login(email: string, password: string, captcha?: string): Promise<AuthTokenPayload> {
    const { data } = await http.post<ApiSuccess<AuthTokenPayload>>(
      '/admin/auth/login',
      withCaptcha({ email, password }, captcha),
    );
    return data.data;
  },

  async me(): Promise<AdminUser> {
    const { data } = await http.get<ApiSuccess<AdminUser>>('/admin/auth/me');
    return data.data;
  },

  async logout(): Promise<void> {
    await http.post('/admin/auth/logout');
  },

  async forgotPassword(email: string, captcha?: string): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      '/admin/auth/forgot-password',
      withCaptcha({ email }, captcha),
    );
    return data.message;
  },

  async resendVerification(email: string): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      '/admin/auth/email/resend',
      { email },
    );
    return data.message;
  },

  async resetPassword(
    payload: {
      token: string;
      email: string;
      password: string;
      password_confirmation: string;
    },
    captcha?: string,
  ): Promise<string> {
    const { data } = await http.post<ApiSuccess<unknown>>(
      '/admin/auth/reset-password',
      withCaptcha(payload, captcha),
    );
    return data.message;
  },
};
