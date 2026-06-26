import { useTranslation } from 'react-i18next';
import { ForgotPasswordForm } from '../components/ForgotPasswordForm';

export default function ForgotPasswordPage() {
  const { t } = useTranslation('auth');
  return (
    <div className="space-y-8">
      <header className="space-y-2">
        <h1 className="text-2xl font-bold">{t('forgot.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('forgot.subtitle')}</p>
      </header>
      <ForgotPasswordForm />
    </div>
  );
}
