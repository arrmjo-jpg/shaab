import { useMutation } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { authService } from '@/services/auth.service';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { paths } from '@/router/paths';
import type { NormalizedError } from '@/types/api';
import type { LoginValues, ForgotValues, ResetValues } from './schemas';

export function useLogin() {
  const { setSession } = useAuth();
  const navigate = useNavigate();
  const { error } = useToast();

  return useMutation({
    mutationFn: async (vars: LoginValues & { captcha?: string }) => {
      const res = await authService.login(vars.email, vars.password, vars.captcha);
      await setSession(res.token);
      return res;
    },
    onSuccess: () => navigate(paths.dashboard, { replace: true }),
    onError: (e: NormalizedError, vars) => {
      const code = (e.errors as Record<string, unknown> | undefined)?.code;
      if (e.status === 403 && code === 'email_unverified') {
        navigate(paths.verifyEmail, {
          replace: true,
          state: { email: vars.email },
        });
        return;
      }
      error(e.message);
    },
  });
}

export function useResendVerification() {
  const { success, error } = useToast();

  return useMutation({
    mutationFn: (email: string) => authService.resendVerification(email),
    onSuccess: (message) => success(message),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useForgotPassword() {
  const { success, error } = useToast();

  return useMutation({
    mutationFn: (vars: ForgotValues & { captcha?: string }) =>
      authService.forgotPassword(vars.email, vars.captcha),
    onSuccess: (message) => success(message),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useResetPassword(token: string, email: string) {
  const navigate = useNavigate();
  const { success, error } = useToast();

  return useMutation({
    mutationFn: (vars: ResetValues & { captcha?: string }) =>
      authService.resetPassword(
        {
          token,
          email,
          password: vars.password,
          password_confirmation: vars.password_confirmation,
        },
        vars.captcha,
      ),
    onSuccess: (message) => {
      success(message);
      navigate(paths.login, { replace: true });
    },
    onError: (e: NormalizedError) => error(e.message),
  });
}
