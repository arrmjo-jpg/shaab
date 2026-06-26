import { z } from 'zod';

export const loginSchema = z.object({
  email: z
    .string()
    .min(1, 'auth:validation.emailRequired')
    .email('auth:validation.emailInvalid'),
  password: z.string().min(1, 'auth:validation.passwordRequired'),
});

export const forgotSchema = z.object({
  email: z
    .string()
    .min(1, 'auth:validation.emailRequired')
    .email('auth:validation.emailInvalid'),
});

export const resetSchema = z
  .object({
    password: z
      .string()
      .min(1, 'auth:validation.passwordRequired')
      .min(8, 'auth:validation.passwordMin'),
    password_confirmation: z.string().min(1, 'auth:validation.confirmRequired'),
  })
  .refine((d) => d.password === d.password_confirmation, {
    path: ['password_confirmation'],
    message: 'auth:validation.confirmMismatch',
  });

export type LoginValues = z.infer<typeof loginSchema>;
export type ForgotValues = z.infer<typeof forgotSchema>;
export type ResetValues = z.infer<typeof resetSchema>;
