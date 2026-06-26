import { z } from 'zod';
import { SLUG_REGEX } from '@/lib/slug';

const nameMin = 'team.validation.nameMin';
const nameMax = 'team.validation.nameMax';
const jobMin = 'team.validation.jobMin';
const jobMax = 'team.validation.jobMax';
const deptMax = 'team.validation.deptMax';
const slugFmt = 'team.validation.slugFormat';
const slugMax = 'team.validation.slugMax';
const seoTitleMax = 'team.validation.seoTitleMax';
const seoDescMax = 'team.validation.seoDescMax';
const seoKwMax = 'team.validation.seoKwMax';
const urlInvalid = 'team.validation.url';

const optStr = (max: number, msgKey: string) =>
  z.string().max(max, msgKey).optional().or(z.literal(''));
const optUrl = z.string().url(urlInvalid).or(z.literal('')).optional();

/**
 * مخطّط نموذج عضو الفريق — يطابق قيود الـ backend (StoreTeamMemberRequest):
 * name/job_title 2..150، slug Unicode، seo حدود، social_links روابط صالحة.
 */
export const teamMemberFormSchema = z.object({
  name: z.string().min(2, nameMin).max(150, nameMax),
  job_title: z.string().min(2, jobMin).max(150, jobMax),
  department: optStr(100, deptMax),
  slug: z
    .string()
    .max(190, slugMax)
    .regex(SLUG_REGEX, slugFmt)
    .optional()
    .or(z.literal('')),
  // bio اختياري — HTML مُنقّى على الـ backend (PageContentSanitizer).
  bio: z.string().optional().or(z.literal('')),
  avatar_asset_id: z.number().int().positive().nullable(),
  social_links: z.object({
    facebook: optUrl,
    twitter_x: optUrl,
    instagram: optUrl,
    tiktok: optUrl,
    linkedin: optUrl,
    youtube: optUrl,
    website: optUrl,
  }),
  seo_title: optStr(200, seoTitleMax),
  seo_description: optStr(500, seoDescMax),
  seo_keywords: optStr(500, seoKwMax),
  canonical_url: optUrl,
  robots: optStr(100, urlInvalid),
  status: z.enum(['active', 'inactive']),
});

export type TeamMemberFormValues = z.infer<typeof teamMemberFormSchema>;

/** مفاتيح روابط التواصل — مصدر موحّد للعرض (يطابق TeamMember::SOCIAL_KEYS). */
export const SOCIAL_KEYS = [
  'facebook',
  'twitter_x',
  'instagram',
  'tiktok',
  'linkedin',
  'youtube',
  'website',
] as const;
