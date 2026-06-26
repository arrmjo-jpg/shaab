import 'server-only';
import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { cache } from 'react';
import { z } from 'zod';

import { env } from './env';

export const AUTH_COOKIE = 'auth_token';

// Current user (GET /api/v1/auth/me → UserResource).
const UserSchema = z
  .object({
    id: z.number(),
    name: z.string(),
    email: z.string(),
    status: z.string().nullish(),
    is_writer: z.boolean().default(false),
    // رقم الهاتف (E.164) + اختيار الاشتراك في حملات واتساب. phone فارغ ⇒ تُعرض نافذة الجمع.
    phone: z.string().nullish(),
    whatsapp_subscribed: z.boolean().default(false),
    avatar: z.string().nullish(),
    bio: z.string().nullish(),
    social_links: z.record(z.string(), z.string()).nullish(),
    writer_request: z
      .object({
        status: z.string().nullish(),
        created_at: z.string().nullish(),
        reviewed_at: z.string().nullish(),
      })
      .nullish(),
    last_login_at: z.string().nullish(),
    created_at: z.string().nullish(),
  })
  .passthrough();

export type AccountUser = z.infer<typeof UserSchema>;

export async function getAuthToken(): Promise<string | null> {
  const store = await cookies();
  return store.get(AUTH_COOKIE)?.value ?? null;
}

// Authenticated server-side fetch (Bearer from the httpOnly cookie). Per-user data → never cached.
// Returns null when there is no token / no API base / network error (caller handles gracefully).
export async function apiFetch(path: string, init?: RequestInit): Promise<Response | null> {
  const token = await getAuthToken();
  if (!token || !env.apiBaseUrl) return null;
  try {
    return await fetch(`${env.apiBaseUrl}${path}`, {
      ...init,
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
        ...(init?.headers ?? {}),
      },
      cache: 'no-store',
    });
  } catch {
    return null;
  }
}

// Deduped per request. null = not authenticated (or backend unreachable).
export const getCurrentUser = cache(async (): Promise<AccountUser | null> => {
  const res = await apiFetch('/api/v1/auth/me');
  if (!res || !res.ok) return null;
  const json = await res.json().catch(() => null);
  const parsed = UserSchema.safeParse(json?.data ?? json);
  return parsed.success ? parsed.data : null;
});

// Route guard for the dashboard — redirects to /login when unauthenticated.
export async function requireUser(): Promise<AccountUser> {
  const user = await getCurrentUser();
  if (!user) redirect('/login');
  return user;
}
