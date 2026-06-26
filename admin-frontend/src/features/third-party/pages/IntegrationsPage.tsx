import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SecretField } from '@/components/form/SecretField';
import { SwitchField } from '@/components/form/SwitchField';
import { SelectField } from '@/components/form/SelectField';
import { TestButton } from '@/components/form/TestButton';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { SaveBar } from '@/features/settings/components/SaveBar';
import {
  useThirdParty,
  useUpdateThirdParty,
  useTestSportmonks,
  useTestOpenweather,
} from '../hooks';
import { integrationsSchema, type IntegrationsValues } from '../schemas';
import { stripEmptySecrets } from '../utils';

const SECRETS = ['sportmonks_api_key', 'openweather_api_key', 'gemini_tts_api_key'];
const UNITS = ['standard', 'metric', 'imperial'];

export default function IntegrationsPage() {
  const { t } = useTranslation('thirdParty');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useThirdParty();
  const update = useUpdateThirdParty();
  const testSm = useTestSportmonks();
  const testOw = useTestOpenweather();

  const i = q.data?.integrations;
  const values: IntegrationsValues = i
    ? {
        sportmonks_enabled: i.sportmonks.enabled,
        sportmonks_api_key: '',
        sportmonks_base_url: i.sportmonks.base_url,
        openweather_enabled: i.openweather.enabled,
        openweather_api_key: '',
        openweather_base_url: i.openweather.base_url,
        openweather_units: i.openweather.units,
        openweather_default_language: i.openweather.default_language,
        gemini_tts_enabled: i.gemini_tts.enabled,
        gemini_tts_api_key: '',
      }
    : {
        sportmonks_enabled: false, sportmonks_api_key: '', sportmonks_base_url: '',
        openweather_enabled: false, openweather_api_key: '', openweather_base_url: '',
        openweather_units: 'metric', openweather_default_language: 'ar',
        gemini_tts_enabled: false, gemini_tts_api_key: '',
      };

  const { register, handleSubmit, control, formState } = useForm<IntegrationsValues>({
    resolver: zodResolver(integrationsSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => update.mutate(stripEmptySecrets({ ...v }, SECRETS)));

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('integrations.sportmonksCard')} description={t('integrations.desc')}>
        <Controller control={control} name="sportmonks_enabled" render={({ field }) => (
          <SwitchField label={t('integrations.enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <TextField
          label={t('integrations.base_url')}
          error={formState.errors.sportmonks_base_url}
          {...register('sportmonks_base_url')}
        />
        <SecretField
          label={t('integrations.api_key')}
          configured={q.data.integrations.sportmonks.api_key_configured}
          {...register('sportmonks_api_key')}
        />
        <TestButton
          label={t('integrations.test')}
          loadingLabel={t('integrations.testing')}
          loading={testSm.isPending}
          onClick={() => testSm.mutate()}
        />
      </SettingsSection>

      <SettingsSection title={t('integrations.openweatherCard')}>
        <Controller control={control} name="openweather_enabled" render={({ field }) => (
          <SwitchField label={t('integrations.enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <TextField
          label={t('integrations.base_url')}
          error={formState.errors.openweather_base_url}
          {...register('openweather_base_url')}
        />
        <Controller control={control} name="openweather_units" render={({ field }) => (
          <SelectField
            label={t('integrations.units')}
            value={field.value}
            onChange={field.onChange}
            options={UNITS.map((u) => ({ value: u, label: t(`integrations.units_${u}`) }))}
          />
        )} />
        <TextField
          label={t('integrations.default_language')}
          error={formState.errors.openweather_default_language}
          {...register('openweather_default_language')}
        />
        <SecretField
          label={t('integrations.api_key')}
          configured={q.data.integrations.openweather.api_key_configured}
          {...register('openweather_api_key')}
        />
        <TestButton
          label={t('integrations.test')}
          loadingLabel={t('integrations.testing')}
          loading={testOw.isPending}
          onClick={() => testOw.mutate()}
        />
      </SettingsSection>

      <SettingsSection title={t('integrations.geminiTtsCard')}>
        <Controller control={control} name="gemini_tts_enabled" render={({ field }) => (
          <SwitchField label={t('integrations.enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <SecretField
          label={t('integrations.api_key')}
          configured={q.data.integrations.gemini_tts.api_key_configured}
          {...register('gemini_tts_api_key')}
        />
      </SettingsSection>

      <SaveBar
        saving={update.isPending}
        disabled={!canEdit}
        note={!canEdit ? t('common.noEditPermission') : undefined}
      />
    </form>
  );
}
