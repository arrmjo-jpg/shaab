import type { CampaignStatus, ChannelHealthState } from '@/types/notifications.types';

type Tone = 'default' | 'success' | 'muted' | 'destructive';

export const STATUS_TONE: Record<CampaignStatus, Tone> = {
  draft: 'muted',
  scheduled: 'default',
  queued: 'default',
  sending: 'default',
  paused: 'muted',
  completed: 'success',
  partially_completed: 'default',
  failed: 'destructive',
  cancelled: 'muted',
};

export const HEALTH_TONE: Record<ChannelHealthState, Tone> = {
  healthy: 'success',
  degraded: 'destructive',
  disabled: 'muted',
  unconfigured: 'muted',
};

export const ALL_STATUSES: CampaignStatus[] = [
  'draft',
  'scheduled',
  'queued',
  'sending',
  'paused',
  'completed',
  'partially_completed',
  'failed',
  'cancelled',
];

export const ALL_SOURCES = ['domain', 'scheduled', 'manual', 'system'] as const;
export const ALL_PRIORITIES = ['critical', 'high', 'normal', 'low'] as const;
export const ALL_CHANNELS = ['firebase', 'whatsapp', 'email'] as const;
