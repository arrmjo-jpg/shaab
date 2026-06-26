import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { SecretField } from '@/components/form/SecretField';
import { SwitchField } from '@/components/form/SwitchField';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { SaveBar } from '@/features/settings/components/SaveBar';
import { useThirdParty, useUpdateThirdParty } from '../hooks';
import { googleMapsSchema, type GoogleMapsValues } from '../schemas';
import { stripEmptySecrets } from '../utils';

const SECRETS = ['maps_frontend_key', 'maps_server_key'];

export default function GoogleMapsPage() {
  const { t } = useTranslation('thirdParty');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useThirdParty();
  const update = useUpdateThirdParty();

  const d = q.data?.google_maps;
  const values: GoogleMapsValues = {
    maps_enabled: d?.enabled ?? false,
    maps_frontend_key: '',
    maps_server_key: '',
  };

  const { handleSubmit, control, register } = useForm<GoogleMapsValues>({
    resolver: zodResolver(googleMapsSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => update.mutate(stripEmptySecrets({ ...v }, SECRETS)));

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('googleMaps.title')} description={t('googleMaps.desc')}>
        <Controller control={control} name="maps_enabled" render={({ field }) => (
          <SwitchField label={t('googleMaps.enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <SecretField
          label={t('googleMaps.frontend_key')}
          configured={q.data.google_maps.frontend_key_configured}
          {...register('maps_frontend_key')}
        />
        <SecretField
          label={t('googleMaps.server_key')}
          configured={q.data.google_maps.server_key_configured}
          {...register('maps_server_key')}
        />
      </SettingsSection>

      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
