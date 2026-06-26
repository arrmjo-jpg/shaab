import { useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { MailWarning, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useResendVerification } from '../hooks';

export default function VerifyEmailPage() {
  const { t } = useTranslation('auth');
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuth();
  const resend = useResendVerification();

  const stateEmail = (location.state as { email?: string } | null)?.email;
  const email = stateEmail ?? user?.email ?? '';

  const onResend = () => {
    if (email) resend.mutate(email);
  };

  const onBack = async () => {
    // إن كان مصادَقاً بحساب غير مؤكَّد، سجّل الخروج حتى لا يُعاد توجيهه
    if (user) await logout();
    navigate(paths.login, { replace: true });
  };

  return (
    <div className="space-y-8">
      <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-500/10">
        <MailWarning className="h-7 w-7 text-amber-500" />
      </div>

      <header className="space-y-2">
        <h1 className="text-2xl font-bold">{t('verifyEmail.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('verifyEmail.subtitle')}</p>
      </header>

      {email ? (
        <p className="rounded-2xl border border-border bg-muted/40 px-4 py-3 text-sm">
          <span className="text-muted-foreground">{t('verifyEmail.account')}: </span>
          <span dir="ltr" className="font-medium">
            {email}
          </span>
        </p>
      ) : null}

      <ul className="space-y-2 text-sm text-muted-foreground">
        <li>• {t('verifyEmail.step1')}</li>
        <li>• {t('verifyEmail.step2')}</li>
      </ul>

      <div className="space-y-3">
        <Button
          type="button"
          size="lg"
          className="w-full"
          disabled={!email || resend.isPending}
          onClick={onResend}
        >
          <Send className="h-4 w-4" />
          {resend.isPending ? t('verifyEmail.sending') : t('verifyEmail.send')}
        </Button>
        <Button type="button" variant="outline" size="lg" className="w-full" onClick={onBack}>
          {t('verifyEmail.backToLogin')}
        </Button>
      </div>
    </div>
  );
}
