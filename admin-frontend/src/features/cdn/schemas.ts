import { z } from 'zod';

export const cdnSettingsSchema = z.object({
  cdn_enabled: z.boolean(),
  cdn_auto_purge: z.boolean(),
  cdn_plan: z.string(),
  cdn_zone_id: z.string(),
  cdn_api_token: z.string(),
});
export type CdnSettingsValues = z.infer<typeof cdnSettingsSchema>;
