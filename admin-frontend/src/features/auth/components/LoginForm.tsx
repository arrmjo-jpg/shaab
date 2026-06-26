import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { PasswordField } from '@/components/form/PasswordField';
import { paths } from '@/router/paths';
import { useRecaptcha } from '@/hooks/useRecaptcha';
import { loginSchema, type LoginValues } from '../schemas';
import { useLogin } from '../hooks';

export function LoginForm() {
  const { t } = useTranslation('auth');
  const { register, handleSubmit, formState } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });
  const login = useLogin();
  const captcha = useRecaptcha('admin_login');

  const onSubmit = handleSubmit(async (values) => {
    const token = await captcha.execute();
    login.mutate({ ...values, captcha: token });
  });
  const busy = login.isPending || formState.isSubmitting;

  return (
    <form onSubmit={onSubmit} className="space-y-5" noValidate>
      <TextField
        label={t('login.email')}
        type="email"
        autoComplete="email"
        error={formState.errors.email}
        {...register('email')}
      />
      <PasswordField
        label={t('login.password')}
        autoComplete="current-password"
        error={formState.errors.password}
        {...register('password')}
      />
      <div className="flex justify-end">
        <Link
          to={paths.forgotPassword}
          className="text-sm font-medium text-primary transition-colors hover:text-primary/80"
        >
          {t('login.forgot')}
        </Link>
      </div>
      {captcha.isV2 ? <div ref={captcha.containerRef} className="flex justify-center" /> : null}
      <Button type="submit" size="lg" className="w-full" disabled={busy}>
        {busy ? t('login.submitting') : t('login.submit')}
      </Button>
    </form>
  );
}
