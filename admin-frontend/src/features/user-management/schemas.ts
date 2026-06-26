import { z } from 'zod';

const req = 'users:validation.required';
const email = 'users:validation.email';
const min2 = 'users:validation.min2';
const pwd = 'users:validation.password';
const match = 'users:validation.passwordMatch';
const slugRe = 'users:validation.slug';
const url = 'users:validation.url';

const optUrl = z.string().url(url).or(z.literal('')).optional();

/**
 * مخطّط موحّد لنموذج المستخدم. كلمة المرور اختيارية في المخطّط؛
 * إلزاميتها عند الإنشاء تُفرَض في الـ component (وضع الإنشاء).
 */
export const userFormSchema = z
  .object({
    name: z.string().min(2, min2),
    email: z.string().min(1, req).email(email),
    password: z.string().optional().or(z.literal('')),
    password_confirmation: z.string().optional().or(z.literal('')),
    status: z.enum(['active', 'suspended', 'banned']),
    email_verified: z.boolean(),
    is_writer: z.boolean(),
    // مسار ملف يعيده endpoint الرفع (وليس URL) — نص حر
    avatar: z.string().max(2048).optional().or(z.literal('')),
    bio: z.string().max(1000).optional().or(z.literal('')),
    socials: z.object({
      facebook: optUrl,
      twitter_x: optUrl,
      instagram: optUrl,
      tiktok: optUrl,
      linkedin: optUrl,
      youtube: optUrl,
    }),
    roles: z.array(z.string()),
  })
  // سياسة كلمة المرور تطابق Password::defaults() في الـ backend:
  // 12 حرفاً على الأقل + حرف كبير + صغير + رقم + رمز. منعها هنا يوقف
  // الإرسال الفاشل بدل رسالة 422 عامة غامضة.
  .refine(
    (d) =>
      !d.password ||
      (d.password.length >= 12 &&
        /[a-z]/.test(d.password) &&
        /[A-Z]/.test(d.password) &&
        /\d/.test(d.password) &&
        /[^A-Za-z0-9]/.test(d.password)),
    { path: ['password'], message: pwd },
  )
  .refine((d) => (d.password ?? '') === (d.password_confirmation ?? ''), {
    path: ['password_confirmation'],
    message: match,
  });
export type UserFormValues = z.infer<typeof userFormSchema>;

export const roleSchema = z.object({
  name: z
    .string()
    .min(2, min2)
    .regex(/^[a-z][a-z0-9_]*$/, slugRe),
  display_name: z.string().min(2, min2),
  description: z.string().max(1000).optional().or(z.literal('')),
  permissions: z.array(z.string()),
});
export type RoleValues = z.infer<typeof roleSchema>;

export const permissionGroupSchema = z.object({
  slug: z
    .string()
    .min(2, min2)
    .regex(/^[a-z][a-z0-9_-]*$/, slugRe),
  display_name: z.string().min(2, min2),
  description: z.string().max(1000).optional().or(z.literal('')),
  icon: z.string().max(100).optional().or(z.literal('')),
  sort_order: z.number().min(0).max(65535),
});
export type PermissionGroupValues = z.infer<typeof permissionGroupSchema>;
