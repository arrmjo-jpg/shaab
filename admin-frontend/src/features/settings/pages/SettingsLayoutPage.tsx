import { Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { SettingsNav } from '../components/SettingsNav';

export default function SettingsLayoutPage() {
  const { t } = useTranslation('settings');
  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('title')}</h1>
      </header>
      <div className="grid gap-6 lg:grid-cols-[240px_1fr]">
        <aside className="lg:sticky lg:top-20 lg:self-start">
          <SettingsNav />
        </aside>
        <div className="min-w-0">
          <Outlet />
        </div>
      </div>
    </div>
  );
}
