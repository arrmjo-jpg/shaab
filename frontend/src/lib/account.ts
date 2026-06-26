import 'server-only';
import { z } from 'zod';

import { apiFetch } from './auth';

// ── Account stats (GET /api/v1/account/stats) ──────────────────────────────────────────────────
const StatsSchema = z
  .object({
    content: z
      .object({ articles: z.number(), news: z.number(), reels: z.number(), videos: z.number() })
      .partial()
      .nullish(),
    workflow: z
      .object({ published: z.number(), in_review: z.number(), rejected: z.number(), draft: z.number() })
      .partial()
      .nullish(),
    engagement: z.object({ comments: z.number(), favorites: z.number(), views: z.number() }).partial().nullish(),
  })
  .passthrough();

export type AccountStats = z.infer<typeof StatsSchema>;

export async function getAccountStats(): Promise<AccountStats | null> {
  const res = await apiFetch('/api/v1/account/stats');
  if (!res?.ok) return null;
  const json = await res.json().catch(() => null);
  const parsed = StatsSchema.safeParse(json?.data ?? json);
  return parsed.success ? parsed.data : null;
}

// ── My content (GET /api/v1/{type}/mine) — articles | videos | reels ───────────────────────────
const ContentItemSchema = z
  .object({
    id: z.number(),
    title: z.string().nullish(),
    slug: z.string().nullish(),
    status: z.string().nullish(),
    type: z.string().nullish(),
    created_at: z.string().nullish(),
    published_at: z.string().nullish(),
    metrics: z.object({ views: z.number().nullish() }).passthrough().nullish(),
  })
  .passthrough();

export type ContentItem = z.infer<typeof ContentItemSchema>;
export type ContentType = 'articles' | 'videos' | 'reels';

// Flexible extraction: supports ApiResponse-wrapped paginators ({data:{data:[]}}) and plain ({data:[]}).
function extractArray(json: unknown): unknown[] {
  const j = json as { data?: unknown } | null;
  const d = j?.data as { data?: unknown } | unknown[] | undefined;
  if (Array.isArray(d)) return d;
  if (Array.isArray((d as { data?: unknown })?.data)) return (d as { data: unknown[] }).data;
  if (Array.isArray(json)) return json as unknown[];
  return [];
}

export async function getMyContent(type: ContentType, status?: string): Promise<ContentItem[]> {
  const params = new URLSearchParams({ per_page: '50' });
  if (status && status !== 'all') params.set('filter[status]', status);
  const res = await apiFetch(`/api/v1/${type}/mine?${params.toString()}`);
  if (!res?.ok) return [];
  const json = await res.json().catch(() => null);
  return extractArray(json)
    .map((x) => ContentItemSchema.safeParse(x))
    .flatMap((r) => (r.success ? [r.data] : []));
}

// ── Notifications (GET /api/v1/notifications) ──────────────────────────────────────────────────
const NotificationSchema = z
  .object({
    id: z.union([z.string(), z.number()]),
    title: z.string().nullish(),
    slug: z.string().nullish(),
    content_type: z.string().nullish(),
    status: z.string().nullish(),
    message: z.string().nullish(),
    read: z.boolean().default(false),
    read_at: z.string().nullish(),
    created_at: z.string().nullish(),
  })
  .passthrough();

export type AppNotification = z.infer<typeof NotificationSchema>;
export type NotificationFilter = 'all' | 'unread' | 'read';

export async function getNotifications(filter: NotificationFilter = 'all'): Promise<AppNotification[]> {
  const params = new URLSearchParams({ per_page: '50' });
  if (filter === 'unread') params.set('filter[read]', '0');
  if (filter === 'read') params.set('filter[read]', '1');
  const res = await apiFetch(`/api/v1/notifications?${params.toString()}`);
  if (!res?.ok) return [];
  const json = await res.json().catch(() => null);
  return extractArray(json)
    .map((x) => NotificationSchema.safeParse(x))
    .flatMap((r) => (r.success ? [r.data] : []));
}

export async function getUnreadCount(): Promise<number> {
  const res = await apiFetch('/api/v1/notifications/unread-count');
  if (!res?.ok) return 0;
  const json = await res.json().catch(() => null);
  const n = (json as { data?: { unread?: number }; unread?: number } | null);
  return Number(n?.data?.unread ?? n?.unread ?? 0) || 0;
}
