import axios from 'axios';
import { env } from '@/lib/env';
import { STORAGE_KEYS } from '@/lib/constants';
import { attachInterceptors } from './interceptors';

export const http = axios.create({
  baseURL: env.apiBaseUrl,
  headers: { Accept: 'application/json' },
});

export function getStoredToken(): string | null {
  return localStorage.getItem(STORAGE_KEYS.token);
}

export function setStoredToken(token: string | null): void {
  if (token) localStorage.setItem(STORAGE_KEYS.token, token);
  else localStorage.removeItem(STORAGE_KEYS.token);
}

/** يُسجّل من متجر المصادقة لتنفيذ خروج قسري عند 401 */
let forcedLogoutHandler: (() => void) | null = null;
export function registerForcedLogout(handler: () => void): void {
  forcedLogoutHandler = handler;
}
export function triggerForcedLogout(): void {
  forcedLogoutHandler?.();
}

attachInterceptors(http);
