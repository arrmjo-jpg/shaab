import { useTranslation } from 'react-i18next';
import { LoginForm } from '../components/LoginForm';

export default function LoginPage() {
  const { t } = useTranslation('auth');
  return (
    <div className="space-y-8">
      <header className="space-y-2">
        <h1 className="text-2xl font-bold">{t('login.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('login.subtitle')}</p>
      </header>
      <LoginForm />
    </div>
  );
}
