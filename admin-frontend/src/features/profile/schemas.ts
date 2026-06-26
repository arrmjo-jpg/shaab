import { z } from 'zod';

const optUrl = z.string().url('profile:validation.url').or(z.literal('')).optional();

export const profileInfoSchema = z.object({
  name: z.string().min(2, 'profile:validation.min2'),
  bio: z.string().max(1000).optional().or(z.literal('')),
  avatar: z.string().max(2048).optional().or(z.literal('')),
  socials: z.object({
    facebook: optUrl,
    twitter_x: optUrl,
    instagram: optUrl,
    linkedin: optUrl,
    youtube: optUrl,
  }),
});
export type ProfileInfoValues = z.infer<typeof profileInfoSchema>;

// يطابق Password::defaults() في الخادم: 12+ مع أحرف كبيرة/صغيرة + أرقام + رموز.
const strongPassword = z
  .string()
  .min(12, 'profile:validation.password')
  .regex(/\p{Ll}/u, 'profile:validation.password')
  .regex(/\p{Lu}/u, 'profile:validation.password')
  .regex(/\d/u, 'profile:validation.password')
  .regex(/[^\p{L}\d]/u, 'profile:validation.password');

export const passwordSchema = z
  .object({
    current_password: z.string().min(1, 'profile:validation.required'),
    password: strongPassword,
    password_confirmation: z.string().min(1, 'profile:validation.required'),
  })
  .refine((d) => d.password === d.password_confirmation, {
    path: ['password_confirmation'],
    message: 'profile:validation.passwordMatch',
  });
export type PasswordValues = z.infer<typeof passwordSchema>;
