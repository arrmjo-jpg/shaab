// أنواع البثّ — ملفّ آمن للعميل (بلا `server-only`) كي تستوردها مكوّنات client (المشغّل) دون أن
// تسحب طبقة البيانات الخادميّة (broadcast.ts) إلى حزمة العميل. تعيد broadcast.ts تصديرها.

export type BroadcastKind = 'live' | 'tv' | 'radio';
export type BroadcastStatus = 'scheduled' | 'live' | 'offline' | 'ended' | 'failed';
export type BroadcastSourceType =
  | 'hls'
  | 'iptv'
  | 'youtube_live'
  | 'external_provider'
  | 'icecast'
  | 'shoutcast';

export interface BroadcastCard {
  id: number;
  kind: BroadcastKind;
  status: BroadcastStatus;
  title: string;
  slug: string;
  excerpt: string | null;
  description: string | null;
  sourceType: BroadcastSourceType;
  isFeatured: boolean;
  viewerCount: number;
  metrics: { likes: number; dislikes: number };
  scheduledAt: string | null;
  startedAt: string | null;
  endedAt: string | null;
  href: string;
  shareImage: string | null;
  category: { id: number; name: string; slug: string } | null;
}

export interface BroadcastPlayback {
  state: 'live' | 'upcoming' | 'ended' | 'offline' | 'failed' | 'unavailable';
  source: { type: BroadcastSourceType; url: string } | null;
  startsAt: string | null;
  vod: { id: number; slug: string; href: string } | null;
}

export interface BroadcastDetail extends BroadcastCard {
  playback: BroadcastPlayback;
}
