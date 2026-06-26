import { z } from 'zod';

const req = 'notifications:validation.required';
const timeMsg = 'notifications:validation.time';
const timeRe = /^\d{2}:\d{2}$/;

export const settingsSchema = z.object({
  enabled: z.boolean(),
  critical_bypass: z.boolean(),
  quiet_hours_enabled: z.boolean(),
  quiet_hours_start: z.string().regex(timeRe, timeMsg),
  quiet_hours_end: z.string().regex(timeRe, timeMsg),
  quiet_hours_timezone: z.string().min(1, req),
});
export type SettingsValues = z.infer<typeof settingsSchema>;

export const templateSchema = z.object({
  event_key: z.string().min(1, req),
  channel: z.enum(['firebase', 'whatsapp', 'email']),
  locale: z.string().optional().or(z.literal('')),
  title: z.string().max(300).optional().or(z.literal('')),
  body: z.string().max(3000).optional().or(z.literal('')),
  image_strategy: z.string().optional().or(z.literal('')),
  deep_link_type: z.string().max(30).optional().or(z.literal('')),
  deep_link_value: z.string().max(500).optional().or(z.literal('')),
  is_default: z.boolean(),
});
export type TemplateValues = z.infer<typeof templateSchema>;

export const eventChannelSchema = z.object({
  mode: z.enum(['automatic', 'manual_approval', 'disabled']),
  channel_priority: z.coerce.number().min(1).max(100),
  fallback_channel: z.string().optional().or(z.literal('')),
  template_id: z.string().optional().or(z.literal('')),
});
export type EventChannelValues = z.infer<typeof eventChannelSchema>;

export const composeSchema = z.object({
  event_key: z.string().min(1, req),
  title: z.string().min(1, req).max(200),
  body: z.string().max(2000).optional().or(z.literal('')),
  audience: z.enum([
    'all',
    'logged',
    'guests',
    'sports_followers',
    'whatsapp_subscribers',
    'email_subscribers',
    'android',
    'ios',
  ]),
  scheduled_at: z.string().optional().or(z.literal('')),
  requires_approval: z.boolean(),
});
export type ComposeValues = z.infer<typeof composeSchema>;
