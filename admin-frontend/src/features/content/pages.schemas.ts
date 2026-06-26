import { z } from 'zod';
import { SLUG_REGEX } from '@/lib/slug';

const req = 'page.validation.required';
const titleMin = 'page.validation.titleMin';
const titleMax = 'page.validation.titleMax';
const slugFmt = 'page.validation.slugFormat';
const slugMax = 'page.validation.slugMax';
const contentReq = 'page.validation.contentRequired';
const seoTitleMax = 'page.validation.seoTitleMax';
const seoDescMax = 'page.validation.seoDescMax';
const seoKwMax = 'page.validation.seoKwMax';
const urlInvalid = 'page.validation.url';
const orderRange = 'page.validation.orderRange';
const templateMax = 'page.validation.templateMax';

const optStr = (max: number, msgKey: string) =>
  z.string().max(max, msgKey).optional().or(z.literal(''));
const optUrl = z.string().url(urlInvalid).or(z.literal('')).optional();

/**
 * مخطّط نموذج الصفحة الثابتة. القيود تطابق ValidationRules في الـ backend:
 * title 2..200، slug a-z0-9-، seo_title ≤200، seo_description ≤500،
 * seo_keywords ≤500، canonical_url URL صالح، robots نص اختياري.
 */
export const pageFormSchema = z.object({
  title: z.string().min(2, titleMin).max(200, titleMax),
  locale: z.enum(['ar', 'en']),
  slug: z
    .string()
    .max(190, slugMax)
    .regex(SLUG_REGEX, slugFmt)
    .optional()
    .or(z.literal('')),
  // HTML — مُنقّى عبر HTMLPurifier على الـ backend. على العميل نتحقّق فقط
  // أنه ليس فارغاً (نزع علامات وفراغات لقياس فعلي).
  content: z
    .string()
    .refine((v) => v.replace(/<[^>]*>/g, '').trim().length > 0, { message: contentReq }),
  template: optStr(100, templateMax),
  show_in_header: z.boolean(),
  show_in_footer: z.boolean(),
  sort_order: z.coerce.number().int().min(0, orderRange).max(65535, orderRange),
  seo_title: optStr(200, seoTitleMax),
  seo_description: optStr(500, seoDescMax),
  seo_keywords: optStr(500, seoKwMax),
  canonical_url: optUrl,
  robots: optStr(100, req),
});

export type PageFormValues = z.infer<typeof pageFormSchema>;
