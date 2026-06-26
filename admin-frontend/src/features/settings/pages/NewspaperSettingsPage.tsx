import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SwitchField } from '@/components/form/SwitchField';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '../components/SettingsSection';
import { SaveBar } from '../components/SaveBar';
import { useNewspaperSettings, useUpdateNewspaperSettings } from '@/features/epaper/hooks';

/**
 * تبديل وحدة الجريدة الرقمية — موطنه الإعدادات (لا قسم الجريدة) كي يبقى متاحاً حتى
 * والوحدة معطَّلة (القسم في التنقّل مخفيّ عند التعطيل). يقيّد التنقّل ظهور القسم على enabled.
 */
export default function NewspaperSettingsPage() {
  const { t } = useTranslation('epaper');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useNewspaperSettings();
  const update = useUpdateNewspaperSettings();

  const [enabled, setEnabled] = useState(false);
  const [displayName, setDisplayName] = useState('');
  const [subscribeUrl, setSubscribeUrl] = useState('');

  useEffect(() => {
    if (!q.data) return;
    setEnabled(q.data.enabled);
    setDisplayName(q.data.display_name);
    setSubscribeUrl(q.data.subscribe_url);
  }, [q.data]);

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = (e: React.FormEvent) => {
    e.preventDefault();
    update.mutate({ enabled, display_name: displayName.trim(), subscribe_url: subscribeUrl.trim() });
  };

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('settingsPage.title')} description={t('settingsPage.desc')}>
        <SwitchField
          label={t('settingsPage.enabledLabel')}
          description={t('settingsPage.enabledDesc')}
          checked={enabled}
          onChange={setEnabled}
          disabled={!canEdit}
        />
        <TextField
          label={t('settingsPage.displayNameLabel')}
          value={displayName}
          onChange={(e) => setDisplayName(e.target.value)}
          maxLength={100}
          disabled={!canEdit}
        />
        <TextField
          label={t('settingsPage.subscribeLabel')}
          value={subscribeUrl}
          onChange={(e) => setSubscribeUrl(e.target.value)}
          type="url"
          dir="ltr"
          placeholder="https://…"
          disabled={!canEdit}
        />
      </SettingsSection>
      <SaveBar
        saving={update.isPending}
        disabled={!canEdit || displayName.trim().length === 0}
        note={!canEdit ? t('settingsPage.noPermission') : undefined}
      />
    </form>
  );
}
