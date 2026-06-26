import { Link, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { paths } from '@/router/paths';
import { ResetPasswordForm } from '../components/ResetPasswordForm';

export default function ResetPasswordPage() {
  const { t } = useTranslation('auth');
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const email = params.get('email') ?? '';

  if (!token || !email) {
    return (
      <div className="space-y-4 text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-destructive/10">
          <ShieldAlert className="h-7 w-7 text-destructive" />
        </div>
        <p className="text-sm text-muted-foreground">{t('reset.invalidLink')}</p>
        <Button asChild variant="outline" className="w-full">
          <Link to={paths.forgotPassword}>{t('forgot.title')}</Link>
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <header className="space-y-2">
        <h1 className="text-2xl font-bold">{t('reset.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('reset.subtitle')}</p>
      </header>
      <ResetPasswordForm token={token} email={email} />
    </div>
  );
}
