import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { TextField } from '@/components/form/TextField';
import { SecretField } from '@/components/form/SecretField';
import { SwitchField } from '@/components/form/SwitchField';
import { SelectField } from '@/components/form/SelectField';
import { useAuth } from '@/hooks/useAuth';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { SaveBar } from '@/features/settings/components/SaveBar';
import { useThirdParty, useUpdateThirdParty } from '../hooks';
import { recaptchaSchema, type RecaptchaValues } from '../schemas';
import { stripEmptySecrets } from '../utils';

const SECRETS = ['recaptcha_secret_key'];
const EMPTY: RecaptchaValues = {
  recaptcha_enabled: false, recaptcha_version: 'v3', recaptcha_site_key: '',
  recaptcha_secret_key: '', recaptcha_score: 0.5,
};

export default function RecaptchaPage() {
  const { t } = useTranslation('thirdParty');
  const { hasPermission } = useAuth();
  const canEdit = hasPermission('settings.edit');
  const q = useThirdParty();
  const update = useUpdateThirdParty();

  const d = q.data?.recaptcha;
  const values: RecaptchaValues = d
    ? {
        recaptcha_enabled: d.enabled,
        recaptcha_version: d.version,
        recaptcha_site_key: d.site_key,
        recaptcha_secret_key: '',
        recaptcha_score: d.score,
      }
    : EMPTY;

  const { register, handleSubmit, control, formState } = useForm<RecaptchaValues>({
    resolver: zodResolver(recaptchaSchema),
    values,
  });

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const onSave = handleSubmit((v) => update.mutate(stripEmptySecrets({ ...v }, SECRETS)));

  return (
    <form onSubmit={onSave} className="space-y-5" noValidate>
      <SettingsSection title={t('recaptcha.title')} description={t('recaptcha.desc')}>
        <Controller control={control} name="recaptcha_enabled" render={({ field }) => (
          <SwitchField label={t('recaptcha.enabled')} checked={field.value} onChange={field.onChange} />
        )} />
        <Controller control={control} name="recaptcha_version" render={({ field }) => (
          <SelectField
            label={t('recaptcha.version')}
            value={field.value}
            onChange={field.onChange}
            options={[{ value: 'v2', label: 'v2' }, { value: 'v3', label: 'v3' }]}
          />
        )} />
        <TextField label={t('recaptcha.site_key')} {...register('recaptcha_site_key')} />
        <SecretField
          label={t('recaptcha.secret_key')}
          configured={q.data.recaptcha.secret_key_configured}
          {...register('recaptcha_secret_key')}
        />
        <TextField
          label={t('recaptcha.score')}
          type="number"
          step="0.1"
          error={formState.errors.recaptcha_score}
          {...register('recaptcha_score', { valueAsNumber: true })}
        />
      </SettingsSection>

      <SaveBar saving={update.isPending} disabled={!canEdit} note={!canEdit ? t('common.noEditPermission') : undefined} />
    </form>
  );
}
