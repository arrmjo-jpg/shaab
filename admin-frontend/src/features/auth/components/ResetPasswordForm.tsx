import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { PasswordField } from '@/components/form/PasswordField';
import { useRecaptcha } from '@/hooks/useRecaptcha';
import { resetSchema, type ResetValues } from '../schemas';
import { useResetPassword } from '../hooks';

export function ResetPasswordForm({ token, email }: { token: string; email: string }) {
  const { t } = useTranslation('auth');
  const { register, handleSubmit, formState } = useForm<ResetValues>({
    resolver: zodResolver(resetSchema),
    defaultValues: { password: '', password_confirmation: '' },
  });
  const reset = useResetPassword(token, email);
  const captcha = useRecaptcha('admin_reset_password');

  const onSubmit = handleSubmit(async (values) => {
    const captchaToken = await captcha.execute();
    reset.mutate({ ...values, captcha: captchaToken });
  });
  const busy = reset.isPending || formState.isSubmitting;

  return (
    <form onSubmit={onSubmit} className="space-y-5" noValidate>
      <PasswordField
        label={t('reset.password')}
        autoComplete="new-password"
        error={formState.errors.password}
        {...register('password')}
      />
      <PasswordField
        label={t('reset.confirm')}
        autoComplete="new-password"
        error={formState.errors.password_confirmation}
        {...register('password_confirmation')}
      />
      {captcha.isV2 ? <div ref={captcha.containerRef} className="flex justify-center" /> : null}
      <Button type="submit" size="lg" className="w-full" disabled={busy}>
        {busy ? t('reset.submitting') : t('reset.submit')}
      </Button>
    </form>
  );
}
