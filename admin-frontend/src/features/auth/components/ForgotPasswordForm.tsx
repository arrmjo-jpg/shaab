import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { MailCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { paths } from '@/router/paths';
import { useRecaptcha } from '@/hooks/useRecaptcha';
import { forgotSchema, type ForgotValues } from '../schemas';
import { useForgotPassword } from '../hooks';

export function ForgotPasswordForm() {
  const { t } = useTranslation('auth');
  const { register, handleSubmit, formState } = useForm<ForgotValues>({
    resolver: zodResolver(forgotSchema),
    defaultValues: { email: '' },
  });
  const forgot = useForgotPassword();
  const captcha = useRecaptcha('admin_forgot_password');

  const onSubmit = handleSubmit(async (values) => {
    const token = await captcha.execute();
    forgot.mutate({ ...values, captcha: token });
  });
  const busy = forgot.isPending || formState.isSubmitting;

  if (forgot.isSuccess) {
    return (
      <div className="space-y-4 text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10">
          <MailCheck className="h-7 w-7 text-primary" />
        </div>
        <h3 className="text-lg font-semibold">{t('forgot.sentTitle')}</h3>
        <p className="text-sm text-muted-foreground">{t('forgot.sentBody')}</p>
        <Button asChild variant="outline" className="w-full">
          <Link to={paths.login}>{t('forgot.back')}</Link>
        </Button>
      </div>
    );
  }

  return (
    <form onSubmit={onSubmit} className="space-y-5" noValidate>
      <TextField
        label={t('forgot.email')}
        type="email"
        autoComplete="email"
        error={formState.errors.email}
        {...register('email')}
      />
      {captcha.isV2 ? <div ref={captcha.containerRef} className="flex justify-center" /> : null}
      <Button type="submit" size="lg" className="w-full" disabled={busy}>
        {busy ? t('forgot.submitting') : t('forgot.submit')}
      </Button>
      <Button asChild variant="ghost" className="w-full">
        <Link to={paths.login}>{t('forgot.back')}</Link>
      </Button>
    </form>
  );
}
