import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { SaveBar } from '@/features/settings/components/SaveBar';
import { useThirdParty, useUpdateThirdParty } from '../hooks';
import { appLinksSchema, type AppLinksValues } from '../schemas';

export default function AppLinksPage() {
  const { t } = useTranslation('thirdParty');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useThirdParty();
  const update = useUpdateThirdParty();

  const d = q.data?.app_links;
  const values: AppLinksValues = {
    app_google_play_url: d?.google_play_url ?? '',
    app_apple_store_url: d?.apple_store_url ?? '',
    app_tv_url: d?.tv_url ?? '',
  };

  const { register, handleSubmit, formState } = useForm<AppLinksValues>({
    resolver: zodResolver(appLinksSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => update.mutate({ ...v }));

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('appLinks.title')} description={t('appLinks.desc')}>
        <TextField label={t('appLinks.google_play_url')} error={formState.errors.app_google_play_url} {...register('app_google_play_url')} />
        <TextField label={t('appLinks.apple_store_url')} error={formState.errors.app_apple_store_url} {...register('app_apple_store_url')} />
        <TextField label={t('appLinks.tv_url')} error={formState.errors.app_tv_url} {...register('app_tv_url')} />
      </SettingsSection>

      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
